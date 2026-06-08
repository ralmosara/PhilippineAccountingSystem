import { createFileRoute, redirect } from '@tanstack/react-router';

import { PayrollFormsPage } from '@/modules/payroll/pages/PayrollFormsPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/payroll/forms')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'payroll.statutory.file')) {
            throw redirect({ to: '/' });
        }
    },
    component: PayrollFormsPage,
});
