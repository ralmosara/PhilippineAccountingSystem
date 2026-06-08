import { createFileRoute, redirect } from '@tanstack/react-router';

import { JournalEntryEditor } from '@/modules/accounting/pages/JournalEntryEditor';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/accounting/journals/new')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'accounting.journals.create')) {
            throw redirect({ to: '/' });
        }
    },
    component: () => <JournalEntryEditor mode="new" />,
});
