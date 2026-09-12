import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

export function ProfilePhoto({
    name,
    photoUrl,
    initials,
    size = 'md',
    className,
}: {
    name: string;
    photoUrl?: string | null;
    initials?: string;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}) {
    const sizeClass =
        size === 'lg' ? 'size-14 text-base' : size === 'sm' ? 'size-8 text-[11px]' : 'size-10 text-xs';
    const fallback =
        initials ||
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase();

    return (
        <Avatar className={cn(sizeClass, className)}>
            {photoUrl ? <AvatarImage src={photoUrl} alt={name} /> : null}
            <AvatarFallback className="bg-primary/10 text-primary font-semibold">
                {fallback || '?'}
            </AvatarFallback>
        </Avatar>
    );
}
