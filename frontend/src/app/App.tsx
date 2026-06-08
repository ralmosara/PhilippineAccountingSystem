import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

import { AppRouter } from './router';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 30_000,
            retry: 1,
            refetchOnWindowFocus: false,
        },
        mutations: {
            retry: 0,
        },
    },
});

export function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <AppRouter />
            {import.meta.env.DEV && <ReactQueryDevtools position="bottom" />}
        </QueryClientProvider>
    );
}
