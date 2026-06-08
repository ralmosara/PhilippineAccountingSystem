import { createFileRoute, redirect } from '@tanstack/react-router';

import { OsdElectionsPage } from '@/modules/tax/pages/OsdElectionsPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/tax/osd-elections')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'tax.osd_election.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: OsdElectionsPage,
});
