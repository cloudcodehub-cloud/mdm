<?php

namespace App\Services;

use App\Enums\AnnouncementAudience;
use App\Enums\EmploymentStatus;
use App\Enums\InAppNotificationType;
use App\Enums\JobType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function __construct(
        private InAppNotificationService $notifications,
        private SettingsService $settings,
    ) {}

    /**
     * @param  array{title: string, body: string, audience: AnnouncementAudience, published_at: CarbonInterface, expires_at: CarbonInterface|null, is_active: bool}  $data
     */
    public function publish(User $author, array $data): Announcement
    {
        if ($author->isSupervisor()) {
            if ($data['audience'] !== AnnouncementAudience::Dsps) {
                throw ValidationException::withMessages([
                    'audience' => __('Supervisors may only announce to their DSP caseload.'),
                ]);
            }

            if ($author->employee === null) {
                throw ValidationException::withMessages([
                    'audience' => __('A supervisor profile is required to announce to a caseload.'),
                ]);
            }
        }

        if ($data['expires_at'] !== null && $data['expires_at']->lessThanOrEqualTo($data['published_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => __('Expiry must be after the publish date.'),
            ]);
        }

        $announcement = Announcement::query()->create([
            'organization_id' => null,
            'author_id' => $author->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'published_at' => $data['published_at'],
            'expires_at' => $data['expires_at'],
            'is_active' => $data['is_active'],
        ]);

        if ($announcement->is_active && $announcement->published_at->lessThanOrEqualTo(now())) {
            $this->notifyRecipients($announcement);
        }

        return $announcement;
    }

    public function updateStatus(Announcement $announcement, bool $isActive): Announcement
    {
        $announcement->is_active = $isActive;
        $announcement->save();

        return $announcement;
    }

    public function markRead(User $user, Announcement $announcement): void
    {
        $announcement->readers()->syncWithoutDetaching([
            $user->id => ['read_at' => now()],
        ]);

        $this->notifications->markUrlRead($user, route('announcements.index'));
    }

    public function isVisibleTo(User $user, Announcement $announcement): bool
    {
        if ($announcement->author_id === $user->id) {
            return true;
        }

        if (! $announcement->is_active) {
            return false;
        }

        if ($announcement->published_at->isFuture()) {
            return false;
        }

        if ($announcement->expires_at !== null && $announcement->expires_at->lessThanOrEqualTo(now())) {
            return false;
        }

        $announcement->loadMissing('author.employee');
        $author = $announcement->author;

        if ($author->isAdmin()) {
            return $announcement->audience->matches($user);
        }

        if ($author->isSupervisor()) {
            return $this->userIsOnSupervisorCaseload($user, $author);
        }

        return false;
    }

    /**
     * @return Builder<Announcement>
     */
    public function visibleQuery(User $user): Builder
    {
        return Announcement::query()
            ->with('author')
            ->where(function (Builder $query) use ($user): void {
                $query->where('author_id', $user->id)
                    ->orWhere(function (Builder $published) use ($user): void {
                        $published->currentlyPublished()
                            ->where(function (Builder $targets) use ($user): void {
                                $this->constrainToAudience($targets, $user);
                            });
                    });
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForUser(User $user, int $limit = 20): array
    {
        $readIds = [];

        foreach (DB::table('announcement_reads')->where('user_id', $user->id)->pluck('announcement_id') as $id) {
            $readIds[] = (int) $id;
        }

        return $this->values($this->visibleQuery($user)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Announcement $announcement): array => $this->serialize($announcement, $user, $readIds)));
    }

    /**
     * @param  list<int>  $readIds
     * @return array<string, mixed>
     */
    public function serialize(Announcement $announcement, User $user, array $readIds = []): array
    {
        $announcement->loadMissing('author');

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'audience' => $announcement->audience->value,
            'audience_label' => $announcement->audience->label(),
            'author_name' => $announcement->author->name,
            'published_at' => $this->settings->formatDateTime($announcement->published_at),
            'expires_at' => $announcement->expires_at !== null
                ? $this->settings->formatDateTime($announcement->expires_at)
                : null,
            'is_active' => $announcement->is_active,
            'is_read' => in_array($announcement->id, $readIds, true) || $announcement->author_id === $user->id,
            'can_update' => $user->can('update', $announcement),
        ];
    }

    private function notifyRecipients(Announcement $announcement): void
    {
        $this->recipients($announcement)->each(function (User $recipient) use ($announcement): void {
            $this->notifications->notify(
                $recipient,
                InAppNotificationType::Announcement,
                $announcement->title,
                $this->preview($announcement->body),
                'announcement:'.$announcement->id,
                route('announcements.index'),
            );
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(Announcement $announcement): Collection
    {
        $announcement->loadMissing('author.employee');
        $author = $announcement->author;

        $query = User::query()
            ->activeForMessaging()
            ->where('id', '!=', $author->id);

        if ($author->isSupervisor()) {
            $supervisorId = $author->employee?->id;

            if ($supervisorId === null) {
                return new Collection;
            }

            return $query->where('role', Role::Dsp)
                ->whereHas('employee', function (Builder $employee) use ($supervisorId): void {
                    $employee->where('supervisor_id', $supervisorId)
                        ->where('job_type', JobType::Dsp)
                        ->where('employment_status', EmploymentStatus::Active);
                })
                ->get();
        }

        return match ($announcement->audience) {
            AnnouncementAudience::Everyone => $query->get(),
            AnnouncementAudience::Admins => $query->where('role', Role::Admin)->get(),
            AnnouncementAudience::Supervisors => $query->where('role', Role::Supervisor)->get(),
            AnnouncementAudience::Dsps => $query->where('role', Role::Dsp)->get(),
        };
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    private function constrainToAudience(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where(function (Builder $adminAuthored) use ($user): void {
                $adminAuthored->whereHas('author', fn (Builder $author) => $author->where('role', Role::Admin))
                    ->where(function (Builder $audience) use ($user): void {
                        $audience->where('audience', AnnouncementAudience::Everyone);

                        if ($user->isAdmin()) {
                            $audience->orWhere('audience', AnnouncementAudience::Admins);
                        }

                        if ($user->isSupervisor()) {
                            $audience->orWhere('audience', AnnouncementAudience::Supervisors);
                        }

                        if ($user->isDsp()) {
                            $audience->orWhere('audience', AnnouncementAudience::Dsps);
                        }
                    });
            });

            if ($user->isDsp() && $user->employee?->supervisor_id) {
                $supervisorEmployeeId = $user->employee->supervisor_id;

                $builder->orWhere(function (Builder $caseload) use ($supervisorEmployeeId): void {
                    $caseload->where('audience', AnnouncementAudience::Dsps)
                        ->whereHas('author', function (Builder $author) use ($supervisorEmployeeId): void {
                            $author->where('role', Role::Supervisor)
                                ->whereHas(
                                    'employee',
                                    fn (Builder $employee) => $employee->where('id', $supervisorEmployeeId),
                                );
                        });
                });
            }
        });
    }

    private function userIsOnSupervisorCaseload(User $user, User $supervisor): bool
    {
        if (! $user->isDsp() || $user->employee === null || $supervisor->employee === null) {
            return false;
        }

        return $user->employee->supervisor_id === $supervisor->employee->id
            && $user->employee->employment_status === EmploymentStatus::Active;
    }

    private function preview(string $body, int $limit = 80): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        if (mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        return mb_substr($normalized, 0, $limit - 1).'…';
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return list<T>
     */
    private function values(iterable $items): array
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = $item;
        }

        return $list;
    }
}
