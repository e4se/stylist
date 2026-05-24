type WardrobeUpload = {
    id: string;
    name: string;
};

type WardrobeItem = {
    id: string;
    name: string;
    description: string | null;
    main_upload: WardrobeUpload[];
};

export default function WardrobeIndex({ items }: { items: WardrobeItem[] }) {
    return (
        <div className="grid h-full flex-1 auto-rows-min gap-3 overflow-x-auto p-4 sm:grid-cols-2 xl:grid-cols-3">
            {items.map((item) => (
                <article
                    key={item.id}
                    className="min-h-28 rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h2 className="truncate text-sm font-medium text-foreground">
                                {item.name}
                            </h2>

                            {item.description && (
                                <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                                    {item.description}
                                </p>
                            )}
                        </div>

                        {item.main_upload[0] && (
                            <span
                                aria-hidden="true"
                                className="size-10 shrink-0 rounded-md border border-sidebar-border/70 bg-muted dark:border-sidebar-border"
                            />
                        )}
                    </div>
                </article>
            ))}
        </div>
    );
}
