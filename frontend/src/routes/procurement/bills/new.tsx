import { createFileRoute, redirect } from '@tanstack/react-router';

import { PostVendorBillPage } from '@/modules/procurement/pages/PostVendorBillPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/procurement/bills/new')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'procurement.bills.post')) {
            throw redirect({ to: '/' });
        }
    },
    component: PostVendorBillPage,
});
