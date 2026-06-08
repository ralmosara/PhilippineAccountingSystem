import { type ReactNode } from 'react';

/**
 * Lightweight table primitive — accounting screens are mostly tables, and
 * full-shadcn data-table boilerplate is overkill for our column-and-render
 * patterns. Columns are declarative; rendering is sync; loading + empty
 * states are first-class.
 *
 * For sortable, paginated, virtualised tables (GL with 1M rows) we'll swap
 * in TanStack Table; this file stays the readable-first option.
 */
export interface Column<Row> {
    key: string;
    header: ReactNode;
    /** How to render a cell. If omitted, prints `row[key]` as a string. */
    render?: (row: Row) => ReactNode;
    /** Right-align for numbers; defaults to left. */
    align?: 'left' | 'right' | 'center';
    /** Tailwind width class — e.g. 'w-32', 'w-[150px]'. */
    width?: string;
    /** When true, uses tabular-nums + truncates with monospace. */
    numeric?: boolean;
}

interface Props<Row> {
    columns: Column<Row>[];
    rows: Row[] | undefined;
    rowKey: (row: Row) => string;
    isLoading?: boolean;
    emptyState?: ReactNode;
    onRowClick?: (row: Row) => void;
    footer?: ReactNode;
}

export function DataTable<Row>({
    columns,
    rows,
    rowKey,
    isLoading,
    emptyState,
    onRowClick,
    footer,
}: Props<Row>) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            <table className="w-full text-sm">
                <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        {columns.map((c) => (
                            <th
                                key={c.key}
                                className={`px-4 py-2 ${c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : ''} ${c.width ?? ''}`}
                            >
                                {c.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {isLoading && (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="px-4 py-6 text-center text-muted-foreground"
                            >
                                Loading…
                            </td>
                        </tr>
                    )}
                    {!isLoading && rows?.length === 0 && (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="px-4 py-6 text-center text-muted-foreground"
                            >
                                {emptyState ?? 'No rows.'}
                            </td>
                        </tr>
                    )}
                    {!isLoading &&
                        rows?.map((row) => (
                            <tr
                                key={rowKey(row)}
                                className={`border-t ${onRowClick ? 'cursor-pointer hover:bg-muted/30' : ''}`}
                                onClick={onRowClick ? () => onRowClick(row) : undefined}
                            >
                                {columns.map((c) => (
                                    <td
                                        key={c.key}
                                        className={[
                                            'px-4 py-2',
                                            c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : '',
                                            c.numeric ? 'tabular-nums' : '',
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                    >
                                        {c.render
                                            ? c.render(row)
                                            : String((row as Record<string, unknown>)[c.key] ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    {footer && <tr className="border-t-2 bg-muted/40 font-medium">{footer}</tr>}
                </tbody>
            </table>
        </div>
    );
}
