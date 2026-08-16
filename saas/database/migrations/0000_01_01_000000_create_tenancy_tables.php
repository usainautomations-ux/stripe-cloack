<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy primitives: the tenant itself, plus the fixed role/permission
 * vocabulary every other table's authorization checks are expressed in.
 *
 * Roles and permissions are keyed by stable strings rather than surrogate ids.
 * The set is fixed by the application, not user-editable, so a readable key is
 * worth more than a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->string('key', 32)->primary();
            $table->string('name');
            $table->string('description')->nullable();
            // Platform roles sit above tenancy and carry a null tenant_id on users.
            $table->boolean('is_platform_role')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->string('group', 32);
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->string('role_key', 32);
            $table->string('permission_key', 64);

            $table->primary(['role_key', 'permission_key']);
            $table->foreign('role_key')->references('key')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_key')->references('key')->on('permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenants');
    }
};
