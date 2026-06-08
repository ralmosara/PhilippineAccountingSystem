import { formatPhp } from '@/shared/lib/money';

export function DashboardPage() {
    return (
        <main className="container py-8">
            <h1 className="text-2xl font-semibold">Dashboard</h1>
            <p className="mt-2 text-muted-foreground">
                Phase 0 shell. Modules will fill this view in later phases.
            </p>

            <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <Card title="Cash on hand" value={formatPhp(0)} />
                <Card title="AR aging (30d)" value={formatPhp(0)} />
                <Card title="VAT due (this period)" value={formatPhp(0)} />
            </div>

            <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2">
                <QuickLink
                    href="#/tax/itr-wizard"
                    title="File an ITR"
                    description="Walk through quarterly (1701Q / 1702Q) or annual (1701 / 1702-RT) filing."
                />
                <QuickLink
                    href="#/tax/osd-elections"
                    title="OSD Elections"
                    description="View / amend the deduction regime locked for the year."
                />
            </div>
        </main>
    );
}

function Card({ title, value }: { title: string; value: string }) {
    return (
        <div className="rounded-lg border bg-card p-5">
            <div className="text-sm text-muted-foreground">{title}</div>
            <div className="mt-2 text-2xl font-semibold tabular-nums">{value}</div>
        </div>
    );
}

function QuickLink({ href, title, description }: { href: string; title: string; description: string }) {
    return (
        <a href={href} className="block rounded-lg border bg-card p-5 hover:bg-accent">
            <div className="text-base font-medium">{title}</div>
            <div className="mt-1 text-sm text-muted-foreground">{description}</div>
        </a>
    );
}
