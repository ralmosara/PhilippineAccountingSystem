import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

import { useLogin } from '../api/auth';

const schema = z.object({
    email: z.string().email('Invalid email address'),
    password: z.string().min(1, 'Password is required'),
});

type LoginForm = z.infer<typeof schema>;

export function LoginPage() {
    const form = useForm<LoginForm>({
        resolver: zodResolver(schema),
        defaultValues: { email: '', password: '' },
    });

    const login = useLogin();

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-6">
            <form
                onSubmit={form.handleSubmit((data) => login.mutate(data))}
                className="w-full max-w-sm space-y-4 rounded-lg border bg-card p-6 shadow-sm"
            >
                <div>
                    <h1 className="text-xl font-semibold">Philippine Accounting System</h1>
                    <p className="text-sm text-muted-foreground">Sign in to continue.</p>
                </div>

                <Field
                    label="Email"
                    type="email"
                    autoComplete="username"
                    error={form.formState.errors.email?.message}
                    {...form.register('email')}
                />

                <Field
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    error={form.formState.errors.password?.message}
                    {...form.register('password')}
                />

                {login.isError && (
                    <p className="text-sm text-destructive">
                        {(login.error as { response?: { data?: { message?: string } } })?.response?.data
                            ?.message ?? 'Sign-in failed. Check your credentials.'}
                    </p>
                )}

                <button
                    type="submit"
                    disabled={login.isPending}
                    className="w-full rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                >
                    {login.isPending ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </div>
    );
}

interface FieldProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string | undefined;
}

const Field = (() => {
    let counter = 0;
    return function Field({ label, error, ...rest }: FieldProps) {
        const id = `f-${++counter}`;
        return (
            <div className="space-y-2">
                <label className="text-sm font-medium" htmlFor={id}>
                    {label}
                </label>
                <input
                    id={id}
                    {...rest}
                    className="w-full rounded-md border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                {error && <p className="text-xs text-destructive">{error}</p>}
            </div>
        );
    };
})();
