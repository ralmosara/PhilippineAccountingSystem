import { createFileRoute, redirect, useParams } from '@tanstack/react-router';

import { PayrollRunDetailPage } from '@/modules/payroll/pages/PayrollRunDetailPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/payroll/runs/$runId')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'payroll.runs.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: PayrollRunDetailRoute,
});

function PayrollRunDetailRoute() {
    const { runId } = useParams({ from: '/payroll/runs/$runId' });
    return <PayrollRunDetailPage runId={runId} />;
}
