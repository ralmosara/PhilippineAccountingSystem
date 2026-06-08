import { createFileRoute, redirect } from '@tanstack/react-router';

import { JournalEntriesPage } from '@/modules/accounting/pages/JournalEntriesPage';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/accounting/journals/')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'accounting.journals.view')) {
            throw redirect({ to: '/' });
        }
    },
    component: JournalEntriesPage,
});
