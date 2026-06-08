import { createFileRoute, redirect } from '@tanstack/react-router';

import { ReportingDashboardPage } from '@/modules/reporting/pages/ReportingDashboardPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/reporting')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'accounting.journals.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: ReportingDashboardPage,
});
