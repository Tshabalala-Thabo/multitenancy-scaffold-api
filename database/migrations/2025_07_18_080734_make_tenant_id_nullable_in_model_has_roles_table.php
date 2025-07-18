<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MakeTenantIdNullableInModelHasRolesTable extends Migration
{
    public function up()
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            // Drop current primary key constraint
            DB::statement('ALTER TABLE model_has_roles DROP PRIMARY KEY');

            // Recreate primary key without tenant_id
            DB::statement('ALTER TABLE model_has_roles ADD PRIMARY KEY (role_id, model_type, model_id)');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            // Drop current primary key
            DB::statement('ALTER TABLE model_has_roles DROP PRIMARY KEY');

            // Recreate the previous primary key, assuming tenant_id was part of it before
            DB::statement('ALTER TABLE model_has_roles ADD PRIMARY KEY (role_id, model_type, model_id, tenant_id)');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });
    }
}
