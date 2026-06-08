import { useState } from 'react';

import {
    REGIME_LABELS,
    useSupersedeOsdElection,
    type OsdElection,
} from '../api/osd-elections';

/**
 * Amendment modal — operator must provide a regime different from the
 * current one PLUS a regulatory citation (≥20 chars). BIR's own approval
 * letter should be attached via the documents endpoint separately.
 */
export function SupersedeOsdElectionDialog({
    election,
    onClose,
}: {
    election: OsdElection;
    onClose: () => void;
}) {
    const [newRegime, setNewRegime] = useState<OsdElection['regime']>(
        election.regime === 'itemized' ? 'osd' : 'itemized',
    );
    const [reason, setReason] = useState('');

    const { mutate, isPending, isError, error } = useSupersedeOsdElection();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutate(
            { election_id: election.id, new_regime: newRegime, reason },
            { onSuccess: onClose },
        );
    };

    const reasonValid = reason.trim().length >= 20;
    const regimeValid = newRegime !== election.regime;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={onClose}
        >
            <form
                onSubmit={handleSubmit}
                onClick={(e) => e.stopPropagation()}
                className="w-full max-w-lg space-y-4 rounded-lg bg-card p-6 shadow-lg"
            >
                <header>
                    <h2 className="text-lg font-semibold">Amend OSD Election</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Supersedes the {election.taxpayer_type} {election.fiscal_year} election
                        currently set to <strong>{REGIME_LABELS[election.regime]}</strong>. Affected
                        quarterly returns must be refiled as amended returns to BIR.
                    </p>
                </header>

                <div className="space-y-2">
                    <label className="text-sm font-medium" htmlFor="new_regime">
                        New regime
                    </label>
                    <select
                        id="new_regime"
                        value={newRegime}
                        onChange={(e) => setNewRegime(e.target.value as OsdElection['regime'])}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        {(['itemized', 'osd', 'flat_8pct'] as const)
                            .filter(
                                (r) =>
                                    r !== election.regime &&
                                    !(r === 'flat_8pct' && election.taxpayer_type === 'corporate'),
                            )
                            .map((r) => (
                                <option key={r} value={r}>
                                    {REGIME_LABELS[r]}
                                </option>
                            ))}
                    </select>
                    {!regimeValid && (
                        <p className="text-xs text-destructive">
                            New regime must differ from the current one.
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <label className="text-sm font-medium" htmlFor="reason">
                        Reason (cite the BIR approval letter / RMC / RMO)
                    </label>
                    <textarea
                        id="reason"
                        rows={5}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Example: BIR letter dated 2026-08-15 approving regime shift from itemized to OSD pursuant to RR 2-2010 § 7. Affected returns: 1701Q-2026-Q1 (Doc# JV-…)."
                        className="w-full rounded-md border bg-background px-3 py-2 font-mono text-xs"
                    />
                    <p className="text-xs text-muted-foreground">
                        {reason.length}/1000 chars · min 20 required
                    </p>
                </div>

                {isError && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {(error as { response?: { data?: { message?: string } } })?.response?.data
                            ?.message ?? 'Amendment failed.'}
                    </div>
                )}

                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={isPending || !reasonValid || !regimeValid}
                        className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {isPending ? 'Filing amendment…' : 'File amendment'}
                    </button>
                </div>
            </form>
        </div>
    );
}
