import { createFileRoute, redirect, useParams } from '@tanstack/react-router';

import { SalesInvoiceDetailPage } from '@/modules/sales/pages/SalesInvoiceDetailPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/invoices/$invoiceId')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: InvoiceDetailRoute,
});

function InvoiceDetailRoute() {
    const { invoiceId } = useParams({ from: '/sales/invoices/$invoiceId' });
    return <SalesInvoiceDetailPage invoiceId={invoiceId} />;
}
