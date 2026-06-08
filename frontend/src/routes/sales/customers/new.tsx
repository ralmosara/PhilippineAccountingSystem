import { createFileRoute, redirect } from '@tanstack/react-router';

import { CustomerEditorPage } from '@/modules/sales/pages/CustomerEditorPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/customers/new')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.create')) {
            throw redirect({ to: '/' });
        }
    },
    component: () => <CustomerEditorPage mode="new" />,
});
