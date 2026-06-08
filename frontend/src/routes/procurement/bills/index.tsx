import { createFileRoute, redirect } from '@tanstack/react-router';

import { VendorBillsPage } from '@/modules/procurement/pages/VendorBillsPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/procurement/bills/')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'procurement.bills.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: VendorBillsPage,
});
