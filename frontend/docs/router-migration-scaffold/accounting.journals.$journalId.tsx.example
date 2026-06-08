import { createFileRoute, redirect, useParams } from '@tanstack/react-router';

import { JournalEntryEditor } from '@/modules/accounting/pages/JournalEntryEditor';
import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

export const Route = createFileRoute('/accounting/journals/$journalId')({
    beforeLoad: () => {
        if (!hasPermission(useAuthStore.getState().user, 'accounting.journals.create')) {
            throw redirect({ to: '/' });
        }
    },
    component: JournalEditRoute,
});

function JournalEditRoute() {
    const { journalId } = useParams({ from: '/accounting/journals/$journalId' });
    return <JournalEntryEditor mode="edit" journalId={journalId} />;
}
