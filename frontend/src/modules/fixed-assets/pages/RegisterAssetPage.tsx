import { useState } from 'react';

import { hasPermission, useAuthStore } from '@/shared/lib/auth-store';

import { useRegisterAsset, type RegisterAssetInput } from '../api/fixed-assets';

interface CategoryOption {
    value: string;
    label: string;
    defaultUsefulLifeMonths: number;
}

const CATEGORIES: CategoryOption[] = [
    { value: 'land',                  label: 'Land',                   defaultUsefulLifeMonths: 0   },
    { value: 'building',              label: 'Building',               defaultUsefulLifeMonths: 480 },
    { value: 'equipment',             label: 'Equipment',              defaultUsefulLifeMonths: 60  },
    { value: 'vehicle',               label: 'Vehicle',                defaultUsefulLifeMonths: 60  },
    { value: 'furniture',             label: 'Furniture & Fixtures',   defaultUsefulLifeMonths: 60  },
    { value: 'it_equipment',          label: 'IT Equipment',           defaultUsefulLifeMonths: 36  },
    { value: 'leasehold_improvement', label: 'Leasehold Improvement',  defaultUsefulLifeMonths: 120 },
];

const DEPRECIATION_METHODS = [
    { value: 'straight_line',             label: 'Straight-Line (SLM)' },
    { value: 'double_declining_balance',  label: 'Double-Declining Balance (DDB)' },
];

const DEFAULT_FORM: RegisterAssetInput & { salvage_value: string } = {
    name:                 '',
    description:          '',
    category:             'equipment',
    acquisition_date:     new Date().toISOString().slice(0, 10),
    acquisition_cost:     '',
    salvage_value:        '0.00',
    useful_life_months:   60,
    depreciation_method:  'straight_line',
};

export function RegisterAssetPage() {
    const user      = useAuthStore((s) => s.user);
    const canRegister = hasPermission(user, 'assets.register');
    const register  = useRegisterAsset();

    const [form, setForm]       = useState(DEFAULT_FORM);
    const [submitted, setSubmitted] = useState(false);

    if (!canRegister) {
        return (
            <div className="container py-8">
                <p className="text-muted-foreground">You do not have permission to register assets.</p>
            </div>
        );
    }

    function handleCategoryChange(category: string) {
        const opt = CATEGORIES.find((c) => c.value === category);
        setForm((f) => ({
            ...f,
            category,
            useful_life_months: opt?.defaultUsefulLifeMonths ?? f.useful_life_months,
            // Land never depreciates — force salvage = cost, life = 0
            ...(category === 'land' ? { depreciation_method: 'straight_line' } : {}),
        }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const payload: RegisterAssetInput = {
            ...form,
            salvage_value: form.salvage_value || '0.00',
        };
        register.mutate(payload, {
            onSuccess: () => {
                setSubmitted(true);
                setForm(DEFAULT_FORM);
            },
        });
    }

    const selectedCategory = CATEGORIES.find((c) => c.value === form.category);
    const isLand = form.category === 'land';

    return (
        <div className="container max-w-2xl py-8">
            <header className="mb-6">
                <h1 className="text-2xl font-semibold">Register Fixed Asset</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Add a new property, plant or equipment item to the asset register.
                </p>
            </header>

            {submitted && (
                <div className="mb-4 rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">
                    Asset registered successfully.{' '}
                    <a href="#/fixed-assets" className="underline">
                        Back to list
                    </a>
                </div>
            )}

            {register.isError && (
                <div className="mb-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                    {(register.error as { response?: { data?: { message?: string } } })
                        ?.response?.data?.message ?? 'Registration failed. Check the form and try again.'}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-5">
                {/* Name */}
                <div>
                    <label className="block text-sm font-medium">Asset Name *</label>
                    <input
                        type="text"
                        required
                        value={form.name}
                        onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                        placeholder="e.g. Dell Laptop — IT Dept"
                        className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>

                {/* Description */}
                <div>
                    <label className="block text-sm font-medium">Description</label>
                    <textarea
                        value={form.description}
                        onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                        rows={2}
                        className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>

                {/* Category */}
                <div>
                    <label className="block text-sm font-medium">Category *</label>
                    <select
                        value={form.category}
                        onChange={(e) => handleCategoryChange(e.target.value)}
                        className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        {CATEGORIES.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                    {selectedCategory && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Default useful life:{' '}
                            {selectedCategory.defaultUsefulLifeMonths === 0
                                ? 'N/A — Land does not depreciate'
                                : `${selectedCategory.defaultUsefulLifeMonths} months (${(selectedCategory.defaultUsefulLifeMonths / 12).toFixed(0)} years)`}
                        </p>
                    )}
                </div>

                {/* Acquisition Date */}
                <div>
                    <label className="block text-sm font-medium">Acquisition Date *</label>
                    <input
                        type="date"
                        required
                        value={form.acquisition_date}
                        onChange={(e) => setForm((f) => ({ ...f, acquisition_date: e.target.value }))}
                        className="mt-1 rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>

                {/* Acquisition Cost + Salvage Value */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium">Acquisition Cost (₱) *</label>
                        <input
                            type="number"
                            required
                            min={0}
                            step="0.01"
                            value={form.acquisition_cost}
                            onChange={(e) => setForm((f) => ({ ...f, acquisition_cost: e.target.value }))}
                            placeholder="0.00"
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium">Salvage Value (₱)</label>
                        <input
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.salvage_value}
                            onChange={(e) => setForm((f) => ({ ...f, salvage_value: e.target.value }))}
                            placeholder="0.00"
                            className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                {/* Useful Life */}
                <div>
                    <label className="block text-sm font-medium">Useful Life (months) *</label>
                    <input
                        type="number"
                        required
                        min={0}
                        value={form.useful_life_months}
                        disabled={isLand}
                        onChange={(e) =>
                            setForm((f) => ({ ...f, useful_life_months: Number(e.target.value) }))
                        }
                        className="mt-1 w-32 rounded-md border bg-background px-3 py-2 text-sm disabled:opacity-50"
                    />
                    {isLand && (
                        <p className="mt-1 text-xs text-amber-700">
                            Land has an indefinite useful life and is not depreciated.
                        </p>
                    )}
                </div>

                {/* Depreciation Method */}
                <div>
                    <label className="block text-sm font-medium">Depreciation Method *</label>
                    <select
                        value={form.depreciation_method}
                        disabled={isLand}
                        onChange={(e) =>
                            setForm((f) => ({ ...f, depreciation_method: e.target.value }))
                        }
                        className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm disabled:opacity-50"
                    >
                        {DEPRECIATION_METHODS.map((m) => (
                            <option key={m.value} value={m.value}>
                                {m.label}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {form.depreciation_method === 'straight_line'
                            ? 'SLM: (Cost − Salvage) ÷ Useful Life months — uniform monthly charge.'
                            : 'DDB: 2 × (1 ÷ Useful Life) × Book Value — accelerated, higher early charges.'}
                    </p>
                </div>

                <div className="flex gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={register.isPending}
                        className="rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {register.isPending ? 'Registering…' : 'Register Asset'}
                    </button>
                    <a
                        href="#/fixed-assets"
                        className="rounded-md border px-5 py-2 text-sm font-medium hover:bg-accent"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    );
}
