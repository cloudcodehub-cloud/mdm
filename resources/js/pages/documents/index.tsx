import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { controlClassName } from '@/components/mdm/directory';
import { dashboard } from '@/routes';
import { index as adminDocuments } from '@/routes/admin/documents';

type DocumentRow = {
    key: string;
    title: string;
    subtitle?: string | null;
    category_label: string;
    origin_label: string;
    source_area_label: string;
    status?: string | null;
    employee_name?: string | null;
    client_name?: string | null;
    dated_at_label: string;
    source_url?: string | null;
    preview_url?: string | null;
    download_url?: string | null;
    ownership_note?: string;
};

type Option = { value: string; label: string };

type Paginator = {
    data: DocumentRow[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

type Props = {
    documents: Paginator;
    filters: {
        search: string;
        category: string;
        origin: string;
        source_area: string;
        from: string;
        to: string;
    };
    filterOptions: {
        categories: Option[];
        origins: Option[];
        source_areas: Option[];
    };
};

export default function AdminDocumentsIndex({
    documents,
    filters,
    filterOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get(
            adminDocuments.url(),
            Object.fromEntries(form.entries()),
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Documents" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Documents
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Admin discovery index over source-owned retained and
                        milestone documents. Ownership stays with the source
                        module. No generic uploads.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-3 md:grid-cols-3 lg:grid-cols-6"
                >
                    <div className="grid gap-1 md:col-span-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Title, employee, client…"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="category">Category</Label>
                        <select
                            id="category"
                            name="category"
                            defaultValue={filters.category}
                            className={controlClassName}
                        >
                            <option value="">All</option>
                            {filterOptions.categories.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="origin">Origin</Label>
                        <select
                            id="origin"
                            name="origin"
                            defaultValue={filters.origin}
                            className={controlClassName}
                        >
                            <option value="">All</option>
                            {filterOptions.origins.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="source_area">Source area</Label>
                        <select
                            id="source_area"
                            name="source_area"
                            defaultValue={filters.source_area}
                            className={controlClassName}
                        >
                            <option value="">All</option>
                            {filterOptions.source_areas.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            name="from"
                            type="date"
                            defaultValue={filters.from}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            name="to"
                            type="date"
                            defaultValue={filters.to}
                        />
                    </div>
                    <div className="flex items-end">
                        <Button type="submit">Filter</Button>
                    </div>
                </form>

                <p className="text-muted-foreground text-xs">
                    {documents.total} document(s)
                </p>

                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="text-muted-foreground border-b text-xs">
                            <tr>
                                <th className="py-2 pr-3 font-medium">Document</th>
                                <th className="py-2 pr-3 font-medium">Category</th>
                                <th className="py-2 pr-3 font-medium">Origin</th>
                                <th className="py-2 pr-3 font-medium">Source</th>
                                <th className="py-2 pr-3 font-medium">Dated</th>
                                <th className="py-2 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {documents.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground py-6"
                                    >
                                        No indexed documents match these filters.
                                    </td>
                                </tr>
                            ) : (
                                documents.data.map((row) => (
                                    <tr
                                        key={row.key}
                                        className="border-border border-b align-top"
                                    >
                                        <td className="py-3 pr-3">
                                            <div className="font-medium">
                                                {row.title}
                                            </div>
                                            {row.subtitle ? (
                                                <div className="text-muted-foreground text-xs">
                                                    {row.subtitle}
                                                </div>
                                            ) : null}
                                            <div className="text-muted-foreground text-xs">
                                                {[
                                                    row.employee_name,
                                                    row.client_name,
                                                    row.status,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                        </td>
                                        <td className="py-3 pr-3">
                                            {row.category_label}
                                        </td>
                                        <td className="py-3 pr-3">
                                            {row.origin_label}
                                        </td>
                                        <td className="py-3 pr-3">
                                            {row.source_area_label}
                                        </td>
                                        <td className="py-3 pr-3 whitespace-nowrap">
                                            {row.dated_at_label}
                                        </td>
                                        <td className="py-3">
                                            <div className="flex flex-col gap-1">
                                                {row.source_url ? (
                                                    <Link
                                                        href={row.source_url}
                                                        className="hover:underline"
                                                    >
                                                        Source
                                                    </Link>
                                                ) : null}
                                                {row.preview_url ? (
                                                    <Link
                                                        href={row.preview_url}
                                                        className="hover:underline"
                                                    >
                                                        Preview
                                                    </Link>
                                                ) : null}
                                                {row.download_url ? (
                                                    <a
                                                        href={row.download_url}
                                                        className="hover:underline"
                                                    >
                                                        Download
                                                    </a>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {documents.links.length > 3 ? (
                    <div className="flex flex-wrap gap-2 text-sm">
                        {documents.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={`${link.label}-${index}`}
                                    href={link.url}
                                    className={
                                        link.active
                                            ? 'font-semibold underline'
                                            : 'text-muted-foreground hover:underline'
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={`${link.label}-${index}`}
                                    className="text-muted-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}

AdminDocumentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents', href: adminDocuments() },
    ],
};
