<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AttendanceModuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{routes: list<string>, forbidden: list<string>}>
     */
    private array $matrix = [
        'superadmin@erp.local' => [
            'routes' => ['employees.index', 'attendances.index', 'work-shifts.index', 'shift-schedules.index', 'overtime-requests.index', 'employee-leaves.index', 'holidays.index', 'payrolls.index', 'dashboard'],
        ],
        'admin.cabang@erp.local' => [
            'routes' => ['attendances.index', 'work-shifts.index', 'holidays.index', 'employees.index'],
            'forbidden' => ['payrolls.index'],
        ],
        'kasir@erp.local' => [
            'routes' => ['dashboard', 'pos.index'],
            'forbidden' => ['attendances.index', 'payrolls.index', 'employees.index', 'holidays.index'],
        ],
        'keuangan@erp.local' => [
            'routes' => ['payrolls.index', 'dashboard'],
            'forbidden' => ['attendances.index', 'holidays.index'],
        ],
        'owner@erp.local' => [
            'routes' => ['dashboard'],
            'forbidden' => ['payrolls.index', 'attendances.index', 'employees.index'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AttendanceModuleSeeder::class);
        $this->seed(RoleUserSeeder::class);
    }

    public function test_every_account_can_access_allowed_routes(): void
    {
        foreach ($this->matrix as $email => $expectations) {
            $user = User::where('email', $email)->first();

            foreach ($expectations['routes'] ?? [] as $route) {
                $this->actingAs($user)
                    ->get(route($route))
                    ->assertOk("{$email} seharusnya bisa akses route {$route}");
            }
        }
    }

    public function test_accounts_are_forbidden_from_restricted_routes(): void
    {
        foreach ($this->matrix as $email => $expectations) {
            $user = User::where('email', $email)->first();

            foreach ($expectations['forbidden'] ?? [] as $route) {
                $this->actingAs($user)
                    ->get(route($route))
                    ->assertForbidden("{$email} seharusnya tidak bisa akses route {$route}");
            }
        }
    }
}
