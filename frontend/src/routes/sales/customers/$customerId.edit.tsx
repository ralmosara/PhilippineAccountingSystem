import { createFileRoute, redirect, useParams } from '@tanstack/react-router';

import { CustomerEditorPage } from '@/modules/sales/pages/CustomerEditorPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/customers/$customerId/edit')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.create')) {
            throw redirect({ to: '/' });
        }
    },
    component: CustomerEditRoute,
});

function CustomerEditRoute() {
    const { customerId } = useParams({ from: '/sales/customers/$customerId/edit' });
    return <CustomerEditorPage mode="edit" customerId={customerId} />;
}
