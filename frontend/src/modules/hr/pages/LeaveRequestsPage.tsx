import { useState } from 'react';

import { DataTable, type Column } from '@/shared/components/DataTable';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import {
    useApproveLeaveRequest,
    useLeaveRequests,
    useRejectLeaveRequest,
    type LeaveRequest,
} from '../api/leave-requests';

export function LeaveRequestsPage() {
    const user = useAuthStore((s) => s.user);
    const canApprove = hasPermission(user, 'hr.leave.approve');

    const [statusFilter, setStatusFilter] = useState<LeaveRequest['status'] | ''>('');
    const [rejectingId, setRejectingId] = useState<string | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');

    const { data: leaveRequests, isLoading } = useLeaveRequests({
        status: statusFilter || undefined,
    });

    const columns: Column<LeaveRequest>[] = [
        {
            key: 'employee_id',
            header: 'Employee ID',
            render: (r) => (
                <span className="font-mono text-xs">{r.employee_id.slice(0, 8)}&hellip;</span>
            ),
        },
        {
            key: 'leave_type',
            header: 'Leave Type',
            render: (r) => <LeaveTypeBadge type={r.leave_type} />,
        },
        {
            key: 'start_date',
            header: 'Start Date',
            numeric: true,
            render: (r) => r.start_date,
        },
        {
            key: 'end_date',
            header: 'End Date',
            numeric: true,
            render: (r) => r.end_date,
        },
        {
            key: 'days_requested',
            header: 'Days',
            numeric: true,
            align: 'right',
            render: (r) => r.days_requested,
        },
        {
            key: 'status',
            header: 'Status',
            render: (r) => <StatusBadge status={r.status} />,
        },
        {
            key: 'created_at',
            header: 'Submitted',
            numeric: true,
            render: (r) => r.created_at.slice(0, 10),
        },
        ...(canApprove
            ? [
                  {
                      key: 'actions',
                      header: 'Actions',
                      render: (r: LeaveRequest) =>
                          r.status === 'pending' ? (
                              <PendingActions
                                  leaveRequestId={r.id}
                                  onRejectClick={() => {
                                      setRejectingId(r.id);
                                      setRejectionReason('');
                                  }}
                              />
                          ) : null,
                  } satisfies Column<LeaveRequest>,
              ]
            : []),
    ];

    return (
        <div className="container py-8">
            <header className="flex items-baseline justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Leave Requests</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Employee leave applications. Approval requires the{' '}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">
                            hr.leave.approve
                        </code>{' '}
                        permission.
                    </p>
                </div>

                <div className="flex items-center gap-3 text-sm">
                    <select
                        value={statusFilter}
                        onChange={(e) =>
                            setStatusFilter(e.target.value as LeaveRequest['status'] | '')
                        }
                        className="rounded-md border bg-background px-2 py-1"
                    >
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>

                    <a
                        href="#/hr/leave-requests/new"
                        className="rounded-md bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90"
                    >
                        + Submit Leave Request
                    </a>
                </div>
            </header>

            <div className="mt-6">
                <DataTable
                    columns={columns}
                    rows={leaveRequests}
                    rowKey={(r) => r.id}
                    isLoading={isLoading}
                    emptyState="No leave requests match the current filters."
                />
            </div>

            {/* Rejection reason modal */}
            {rejectingId && (
                <RejectModal
                    leaveRequestId={rejectingId}
                    reason={rejectionReason}
                    onReasonChange={setRejectionReason}
                    onClose={() => setRejectingId(null)}
                />
            )}
        </div>
    );
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function PendingActions({
    leaveRequestId,
    onRejectClick,
}: {
    leaveRequestId: string;
    onRejectClick: () => void;
}) {
    const approve = useApproveLeaveRequest(leaveRequestId);

    return (
        <div className="flex gap-2">
            <button
                onClick={() => approve.mutate()}
                disabled={approve.isPending}
                className="rounded bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
            >
                {approve.isPending ? 'Approving…' : 'Approve'}
            </button>
            <button
                onClick={onRejectClick}
                className="rounded bg-red-600 px-2 py-0.5 text-xs font-medium text-white hover:bg-red-700"
            >
                Reject
            </button>
        </div>
    );
}

function RejectModal({
    leaveRequestId,
    reason,
    onReasonChange,
    onClose,
}: {
    leaveRequestId: string;
    reason: string;
    onReasonChange: (v: string) => void;
    onClose: () => void;
}) {
    const reject = useRejectLeaveRequest(leaveRequestId);

    const submit = () => {
        if (!reason.trim()) return;
        reject.mutate(reason, { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="w-full max-w-md rounded-lg border bg-card p-6 shadow-xl">
                <h2 className="text-lg font-semibold">Reject Leave Request</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Provide a reason that will be shown to the employee.
                </p>
                <textarea
                    value={reason}
                    onChange={(e) => onReasonChange(e.target.value)}
                    rows={4}
                    placeholder="Enter rejection reason…"
                    className="mt-4 w-full rounded-md border bg-background px-3 py-2 text-sm"
                />
                <div className="mt-4 flex justify-end gap-2">
                    <button
                        onClick={onClose}
                        className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={submit}
                        disabled={!reason.trim() || reject.isPending}
                        className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        {reject.isPending ? 'Rejecting…' : 'Confirm Rejection'}
                    </button>
                </div>
                {reject.error && (
                    <p className="mt-2 text-sm text-destructive">
                        {(reject.error as { response?: { data?: { message?: string } } })?.response
                            ?.data?.message ?? 'Rejection failed.'}
                    </p>
                )}
            </div>
        </div>
    );
}

function LeaveTypeBadge({ type }: { type: LeaveRequest['leave_type'] }) {
    const styles: Record<LeaveRequest['leave_type'], string> = {
        sick:        'bg-red-100 text-red-800',
        vacation:    'bg-blue-100 text-blue-800',
        emergency:   'bg-orange-100 text-orange-800',
        maternity:   'bg-pink-100 text-pink-800',
        paternity:   'bg-cyan-100 text-cyan-800',
        solo_parent: 'bg-purple-100 text-purple-800',
        bereavement: 'bg-slate-100 text-slate-700',
    };
    const labels: Record<LeaveRequest['leave_type'], string> = {
        sick:        'Sick',
        vacation:    'Vacation',
        emergency:   'Emergency',
        maternity:   'Maternity',
        paternity:   'Paternity',
        solo_parent: 'Solo Parent',
        bereavement: 'Bereavement',
    };
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${styles[type]}`}>
            {labels[type]}
        </span>
    );
}

function StatusBadge({ status }: { status: LeaveRequest['status'] }) {
    const styles: Record<LeaveRequest['status'], string> = {
        pending:   'bg-amber-100 text-amber-800',
        approved:  'bg-emerald-100 text-emerald-800',
        rejected:  'bg-red-100 text-red-800',
        cancelled: 'bg-slate-100 text-slate-600',
    };
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status]}`}>
            {status}
        </span>
    );
}
