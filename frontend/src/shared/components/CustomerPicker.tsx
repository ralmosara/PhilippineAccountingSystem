import { useMemo, useState } from 'react';

import { useCustomers, type Customer } from '@/modules/sales/api/sales-invoices';

interface Props {
    value: string;
    onChange: (customerId: string, customer: Customer) => void;
    placeholder?: string;
    className?: string;
}

/**
 * Customer picker — autocomplete by customer_no, registered_name, or TIN.
 * Server-side filtered via the `q` param so we don't ship 10k customers
 * down the wire when the user types two characters.
 */
export function CustomerPicker({ value, onChange, placeholder, className }: Props) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);

    const { data: candidates } = useCustomers(query);
    const selected = candidates?.find((c) => c.id === value);

    const display = useMemo(() => {
        if (!selected) return '';
        return `${selected.customer_no} · ${selected.registered_name}`;
    }, [selected]);

    return (
        <div className={`relative ${className ?? 'min-w-[16rem]'}`}>
            <input
                type="text"
                value={open ? query : display}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => { setOpen(true); setQuery(''); }}
                onBlur={() => setTimeout(() => setOpen(false), 100)}
                placeholder={placeholder ?? '— select customer —'}
                className="w-full rounded-md border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
            />

            {open && candidates && candidates.length > 0 && (
                <ul className="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-md border bg-popover shadow-md">
                    {candidates.slice(0, 30).map((c) => (
                        <li
                            key={c.id}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => {
                                onChange(c.id, c);
                                setOpen(false);
                                setQuery('');
                            }}
                            className={`cursor-pointer px-3 py-1.5 text-xs hover:bg-accent ${
                                c.id === value ? 'bg-accent/60 font-medium' : ''
                            }`}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span>
                                    <span className="font-mono text-muted-foreground">{c.customer_no}</span>{' '}
                                    <span>{c.registered_name}</span>
                                </span>
                                <span className="flex gap-1">
                                    {c.is_government && (
                                        <span className="rounded-full bg-purple-100 px-1.5 py-0.5 text-[10px] text-purple-800">
                                            Gov
                                        </span>
                                    )}
                                    {(c.is_senior_citizen || c.is_pwd) && (
                                        <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800">
                                            {c.is_senior_citizen ? 'Senior' : 'PWD'}
                                        </span>
                                    )}
                                    {c.is_vat_registered && (
                                        <span className="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-700">
                                            VAT
                                        </span>
                                    )}
                                </span>
                            </div>
                            {c.tin && (
                                <div className="font-mono text-[10px] text-muted-foreground">
                                    TIN {c.tin}
                                </div>
                            )}
                        </li>
                    ))}
                    <li className="border-t">
                        <a
                            href="#/sales/customers/new"
                            onMouseDown={(e) => e.preventDefault()}
                            className="block cursor-pointer px-3 py-1.5 text-xs text-primary hover:bg-accent"
                        >
                            + New customer…
                        </a>
                    </li>
                </ul>
            )}
        </div>
    );
}
