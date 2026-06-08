import { createFileRoute, redirect } from '@tanstack/react-router';

import { FiscalPeriodsPage } from '@/modules/accounting/pages/FiscalPeriodsPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/accounting/fiscal-periods')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'accounting.periods.lock')) {
            throw redirect({ to: '/' });
        }
    },
    component: FiscalPeriodsPage,
});
