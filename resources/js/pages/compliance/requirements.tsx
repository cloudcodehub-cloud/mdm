import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge, controlClassName } from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index as complianceIndex } from '@/routes/compliance';

type Option = { value: string; label: string };

type Requirement = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    source_kind: string;
    source_kind_label: string;
    source_key: string;
    applicability: string;
    applicability_label: string;
    enforcement_level: string;
    enforcement_label: string;
    override_policy: string;
    override_policy_label: string;
    is_active: boolean;
    is_hard_stop: boolean;
};

export default function ComplianceRequirements({
    requirements,
    options,
}: {
    requirements: Requirement[];
    options: {
        source_kinds: Option[];
        credential_types: Option[];
        applicabilities: Option[];
        enforcement_levels: Option[];
        override_policies: Option[];
    };
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing = requirements.find((row) => row.id === editingId) ?? null;

    return (
        <>
            <Head title="Compliance Requirements" />
            <div className="page-shell">
                <div>
                    <BackLink href={complianceIndex()}>Back to Compliance</BackLink>
                    <h1 className="mt-2 text-xl font-semibold tracking-tight">
                        Compliance Requirements
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Configure policy only. Requirements start inactive unless you
                        explicitly activate them. Hard Stops are never invented
                        automatically for existing employees.
                    </p>
                </div>

                <Panel
                    title="Create requirement"
                    description="Choose warning vs Hard Stop and whether a Temporary Exception is allowed."
                >
                    <RequirementForm
                        action="/compliance/requirements"
                        method="post"
                        options={options}
                        defaults={{
                            code: '',
                            name: '',
                            description: '',
                            source_kind: 'credential',
                            source_key: options.credential_types[0]?.value ?? '',
                            applicability: 'dsp',
                            enforcement_level: 'warning',
                            override_policy: 'non_overrideable',
                            is_active: false,
                        }}
                        submitLabel="Create inactive requirement"
                    />
                </Panel>

                <Panel title="Catalog">
                    {requirements.length === 0 ? (
                        <EmptyState message="No compliance requirements configured yet." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="pb-2 font-medium">Requirement</th>
                                        <th className="pb-2 font-medium">Enforcement</th>
                                        <th className="pb-2 font-medium">Override</th>
                                        <th className="pb-2 font-medium">Status</th>
                                        <th className="pb-2 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {requirements.map((row) => (
                                        <tr key={row.id}>
                                            <td className="py-3">
                                                <p className="font-medium">{row.name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {row.code} · {row.source_kind_label} ·{' '}
                                                    {row.source_key} · {row.applicability_label}
                                                </p>
                                            </td>
                                            <td className="py-3">{row.enforcement_label}</td>
                                            <td className="py-3 text-xs">
                                                {row.override_policy_label}
                                            </td>
                                            <td className="py-3">
                                                <StatusBadge
                                                    status={row.is_active ? 'active' : 'pending'}
                                                    label={row.is_active ? 'Active' : 'Inactive'}
                                                />
                                            </td>
                                            <td className="py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() =>
                                                            setEditingId(
                                                                editingId === row.id
                                                                    ? null
                                                                    : row.id,
                                                            )
                                                        }
                                                    >
                                                        {editingId === row.id ? 'Close' : 'Edit'}
                                                    </Button>
                                                    <Form
                                                        action={
                                                            row.is_active
                                                                ? `/compliance/requirements/${row.id}/deactivate`
                                                                : `/compliance/requirements/${row.id}/activate`
                                                        }
                                                        method="post"
                                                    >
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant={
                                                                row.is_active
                                                                    ? 'secondary'
                                                                    : 'default'
                                                            }
                                                        >
                                                            {row.is_active
                                                                ? 'Deactivate'
                                                                : 'Activate'}
                                                        </Button>
                                                    </Form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>

                {editing && (
                    <Panel title={`Edit ${editing.name}`}>
                        <RequirementForm
                            action={`/compliance/requirements/${editing.id}`}
                            method="put"
                            options={options}
                            defaults={{
                                code: editing.code,
                                name: editing.name,
                                description: editing.description ?? '',
                                source_kind: editing.source_kind,
                                source_key: editing.source_key,
                                applicability: editing.applicability,
                                enforcement_level: editing.enforcement_level,
                                override_policy: editing.override_policy,
                                is_active: editing.is_active,
                            }}
                            submitLabel="Save changes"
                        />
                    </Panel>
                )}
            </div>
        </>
    );
}

function RequirementForm({
    action,
    method,
    options,
    defaults,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put';
    options: {
        source_kinds: Option[];
        credential_types: Option[];
        applicabilities: Option[];
        enforcement_levels: Option[];
        override_policies: Option[];
    };
    defaults: {
        code: string;
        name: string;
        description: string;
        source_kind: string;
        source_key: string;
        applicability: string;
        enforcement_level: string;
        override_policy: string;
        is_active: boolean;
    };
    submitLabel: string;
}) {
    const [sourceKind, setSourceKind] = useState(defaults.source_kind);

    return (
        <Form action={action} method={method} className="grid gap-3 md:grid-cols-2">
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Code</span>
                <Input name="code" defaultValue={defaults.code} placeholder="cred_cpr" />
            </label>
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Name</span>
                <Input name="name" required defaultValue={defaults.name} />
            </label>
            <label className="grid gap-1 text-sm md:col-span-2">
                <span className="font-medium">Description</span>
                <textarea
                    name="description"
                    defaultValue={defaults.description}
                    className={cn(controlClassName, 'min-h-20')}
                />
            </label>
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Source</span>
                <select
                    name="source_kind"
                    required
                    className={controlClassName}
                    value={sourceKind}
                    onChange={(event) => setSourceKind(event.target.value)}
                >
                    {options.source_kinds.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Source key</span>
                {sourceKind === 'credential' ? (
                    <select
                        name="source_key"
                        required
                        className={controlClassName}
                        defaultValue={defaults.source_key}
                    >
                        {options.credential_types.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                ) : (
                    <Input
                        name="source_key"
                        required
                        defaultValue={defaults.source_key}
                        placeholder="Training title (exact match)"
                    />
                )}
            </label>
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Applicability</span>
                <select
                    name="applicability"
                    required
                    className={controlClassName}
                    defaultValue={defaults.applicability}
                >
                    {options.applicabilities.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Enforcement</span>
                <select
                    name="enforcement_level"
                    required
                    className={controlClassName}
                    defaultValue={defaults.enforcement_level}
                >
                    {options.enforcement_levels.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="grid gap-1 text-sm">
                <span className="font-medium">Override policy</span>
                <select
                    name="override_policy"
                    required
                    className={controlClassName}
                    defaultValue={defaults.override_policy}
                >
                    {options.override_policies.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="flex items-center gap-2 text-sm md:col-span-2">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    defaultChecked={defaults.is_active}
                />
                <span>Active (applies to eligibility when checked)</span>
            </label>
            <div className="md:col-span-2">
                <Button type="submit">{submitLabel}</Button>
            </div>
        </Form>
    );
}
