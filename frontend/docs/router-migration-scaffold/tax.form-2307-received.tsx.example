import { createFileRoute, redirect } from '@tanstack/react-router';

import { Form2307ReceivedPage } from '@/modules/tax/pages/Form2307ReceivedPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/tax/form-2307-received')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'tax.form_2307_received.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: Form2307ReceivedPage,
});
