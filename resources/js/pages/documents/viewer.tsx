import { Head, Link } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { RecordPage } from '@/components/mdm/record-detail';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

export default function DocumentViewer({
    title,
    html,
    pdfUrl = null,
    backUrl,
    downloadUrl,
}: {
    title: string;
    html: string;
    pdfUrl?: string | null;
    backUrl: string;
    downloadUrl: string;
}) {
    const showRetainedPdf = Boolean(pdfUrl);

    return (
        <>
            <Head title={title} />
            <RecordPage wide className="gap-4">
                <div className="document-toolbar bg-card/90 sticky top-0 z-20 flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2 backdrop-blur-md">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={backUrl}>Back to record</Link>
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a href={downloadUrl}>
                                <Download aria-hidden />
                                Download PDF
                            </a>
                        </Button>
                        {!showRetainedPdf ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => window.print()}
                            >
                                <Printer aria-hidden />
                                Print
                            </Button>
                        ) : null}
                    </div>
                </div>
                <div className="document-stage flex justify-center px-0 py-2 md:py-4">
                    {showRetainedPdf ? (
                        <iframe
                            title={title}
                            src={pdfUrl ?? undefined}
                            className="document-paper h-[80vh] w-full max-w-[900px] rounded-sm border bg-white"
                        />
                    ) : (
                        <article
                            className="document-paper w-full max-w-[820px] overflow-auto rounded-sm border px-6 py-8 md:px-10 md:py-12"
                            dangerouslySetInnerHTML={{ __html: html }}
                        />
                    )}
                </div>
            </RecordPage>
        </>
    );
}

DocumentViewer.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Document', href: '#' },
    ],
};
