import { createFileRoute, redirect } from '@tanstack/react-router';

import { PosTerminalPage } from '@/modules/sales/pages/PosTerminalPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/sales/pos')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'sales.pos.transact')) {
            throw redirect({ to: '/' });
        }
    },
    component: PosTerminalPage,
});
