import { createFileRoute, redirect } from '@tanstack/react-router';

import { OfficialReceiptsPage } from '@/modules/sales/pages/OfficialReceiptsPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/official-receipts')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.invoices.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: OfficialReceiptsPage,
});
