<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel>
 */
final class UserFactory extends Factory
{
    /** @var class-string<\App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel> */
    protected $model = UserModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id'                => Uuid::uuid4()->toString(),
            'company_id'        => fake()->uuid(),
            'email'             => fake()->unique()->safeEmail(),
            'password_hash'     => Hash::make('password123'),
            'full_name'         => fake()->name(),
            'employee_no'       => 'EMP-'.str_pad((string) fake()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'mfa_enabled'       => false,
            'email_verified_at' => now(),
            'is_active'         => true,
        ];
    }

    public function withMfa(): self
    {
        return $this->state(fn () => ['mfa_enabled' => true]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
