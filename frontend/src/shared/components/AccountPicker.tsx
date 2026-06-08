import { useMemo, useState } from 'react';

import { useAccounts, type Account } from '@/modules/accounting/api/journals';

interface Props {
    value: string;
    onChange: (accountId: string) => void;
    /** When the picker has nothing useful to filter, treat empty as "show all". */
    placeholder?: string;
    /** Restrict to accounts matching predicate (e.g. revenue-only for sales lines). */
    filter?: (a: Account) => boolean;
    /** Visible width — defaults to 'min-w-[14rem]'. */
    className?: string;
    disabled?: boolean;
}

/**
 * Account picker — autocomplete by code OR name. Used everywhere a JV line
 * needs an account: the journal editor (Batch 22), the AR/AP receipt entry
 * (future), the reversal modal.
 *
 * Behaviour:
 *   - Type characters → filters the list inline (code prefix OR substring of name)
 *   - Enter / click → selects
 *   - Esc → closes without changing the value
 *
 * Backed by the same /accounts endpoint as the JV editor; ReactQuery dedupes
 * the request so dropping 30 of these on a screen makes one HTTP call.
 */
export function AccountPicker({ value, onChange, placeholder, filter, className, disabled }: Props) {
    const { data: accounts } = useAccounts();
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);

    const candidates = useMemo(() => {
        const all = (accounts ?? []).filter((a) => (filter ? filter(a) : true));
        if (!query) return all.slice(0, 50);
        const q = query.toLowerCase();
        return all
            .filter((a) => a.code.toLowerCase().startsWith(q) || a.name.toLowerCase().includes(q))
            .slice(0, 30);
    }, [accounts, query, filter]);

    const selected = accounts?.find((a) => a.id === value);
    const display = selected ? `${selected.code} · ${selected.name}` : '';

    return (
        <div className={`relative ${className ?? 'min-w-[14rem]'}`}>
            <input
                type="text"
                value={open ? query : display}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => { setOpen(true); setQuery(''); }}
                onBlur={() => setTimeout(() => setOpen(false), 100)}
                placeholder={placeholder ?? '— select account —'}
                disabled={disabled}
                className="w-full rounded-md border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
            />

            {open && candidates.length > 0 && (
                <ul className="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-md border bg-popover shadow-md">
                    {candidates.map((a) => (
                        <li
                            key={a.id}
                            onMouseDown={(e) => e.preventDefault()}      // keep focus
                            onClick={() => {
                                onChange(a.id);
                                setOpen(false);
                                setQuery('');
                            }}
                            className={`cursor-pointer px-3 py-1.5 text-xs hover:bg-accent ${
                                a.id === value ? 'bg-accent/60 font-medium' : ''
                            }`}
                        >
                            <span className="font-mono text-muted-foreground">{a.code}</span>{' '}
                            <span>{a.name}</span>
                            <span className="ml-2 text-[10px] uppercase tracking-wide text-muted-foreground">
                                {a.type}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
