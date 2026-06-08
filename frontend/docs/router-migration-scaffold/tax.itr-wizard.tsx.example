import { createFileRoute, redirect } from '@tanstack/react-router';

import { ItrWizardPage } from '@/modules/tax/pages/ItrWizardPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/tax/itr-wizard')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'tax.forms.generate')) {
            throw redirect({ to: '/' });
        }
    },
    component: ItrWizardPage,
});
