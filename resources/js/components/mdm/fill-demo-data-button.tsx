export function FillDemoDataButton({
    enabled,
    onFill,
}: {
    enabled: boolean;
    onFill: () => void;
}) {
    if (!enabled) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={onFill}
            className="border-border text-muted-foreground hover:text-foreground inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium"
        >
            Fill Demo Data
        </button>
    );
}
