import { useEffect, useState } from 'react';

import { toScaled, fromScaled } from '@/shared/lib/bcmath';

interface Props {
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void;
    placeholder?: string;
    disabled?: boolean;
    autoFocus?: boolean;
    /** Number of decimal places to display on blur (default 2). */
    displayDp?: 2 | 4;
    /** Tailwind extras (e.g. text-right). */
    className?: string;
    /** Show a "₱" prefix inside the field. */
    withPesoSign?: boolean;
}

/**
 * Money input — accepts free typing of "1234.56" / "1,234.56" / "₱1,234.56",
 * normalises on blur to a clean BCMath-friendly numeric string. Internal
 * arithmetic stays in BigInt-scaled units (see shared/lib/bcmath).
 */
export function MoneyInput({
    value,
    onChange,
    onBlur,
    onKeyDown,
    placeholder = '0.00',
    disabled,
    autoFocus,
    displayDp = 2,
    className = '',
    withPesoSign = false,
}: Props) {
    // Local draft so the user can freely type while editing — committed
    // (normalised) on blur via onChange.
    const [draft, setDraft] = useState<string>(value);

    useEffect(() => {
        setDraft(value);
    }, [value]);

    const handleBlur = () => {
        const scaled = toScaled(draft);
        const normalised = scaled === 0n && draft.trim() === '' ? '' : fromScaled(scaled, displayDp);
        setDraft(normalised);
        if (normalised !== value) onChange(normalised);
        onBlur?.();
    };

    return (
        <div className="relative">
            {withPesoSign && (
                <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">
                    ₱
                </span>
            )}
            <input
                type="text"
                inputMode="decimal"
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onBlur={handleBlur}
                onKeyDown={onKeyDown}
                placeholder={placeholder}
                disabled={disabled}
                autoFocus={autoFocus}
                className={`w-full rounded-md border bg-background ${withPesoSign ? 'pl-6' : 'px-2'} py-1 text-right font-mono text-xs tabular-nums focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50 ${className}`}
            />
        </div>
    );
}
