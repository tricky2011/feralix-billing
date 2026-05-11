<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standard FreeRADIUS SQL schema.
     * These tables mirror the default FreeRADIUS MySQL schema and are used for
     * database-backed authentication (via rlm_sql in FreeRADIUS).
     * The billing system writes here; FreeRADIUS reads via its SQL module.
     *
     * radcheck  : auth check (password, expiry, etc.)
     * radreply  : reply attributes on accept (rate limit, session-timeout, etc.)
     * radusergroup : group membership for profile routing
     */
    public function up(): void
    {
        // radcheck: stores check items for authentication
        Schema::create('radcheck', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 64)->charset('utf8');
            $table->string('attribute', 64)->charset('utf8');
            $table->string('op', 2)->charset('utf8')->default('==');
            $table->string('value', 253)->charset('utf8');
            $table->timestamps();

            $table->unique(['username', 'attribute', 'op'], 'radcheck_username_attr_op_unique');
            $table->index('username');
            $table->index('attribute');
        });

        // radreply: reply items sent to NAS on Access-Accept
        Schema::create('radreply', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 64)->charset('utf8');
            $table->string('attribute', 64)->charset('utf8');
            $table->string('op', 2)->charset('utf8')->default('=');
            $table->string('value', 253)->charset('utf8');
            $table->timestamps();

            $table->unique(['username', 'attribute', 'op'], 'radreply_username_attr_op_unique');
            $table->index('username');
            $table->index('attribute');
        });

        // radusergroup: assigns user to a group
        Schema::create('radusergroup', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 64)->charset('utf8');
            $table->string('groupname', 64)->charset('utf8');
            $table->integer('priority')->default(1);
            $table->timestamps();

            $table->unique(['username', 'groupname'], 'radusergroup_username_group_unique');
            $table->index('username');
            $table->index('groupname');
        });

        // radacct: accounting records (mirrors NAS Acct tables)
        Schema::create('radacct', function (Blueprint $table): void {
            $table->bigIncrements('radacctid');
            $table->string('acctsessionid', 64)->charset('utf8')->nullable();
            $table->string('acctuniqueid', 32)->charset('utf8')->unique();
            $table->string('username', 64)->charset('utf8')->nullable()->index();
            $table->string('groupname', 64)->charset('utf8')->nullable();
            $table->string('realm', 64)->charset('utf8')->nullable();
            $table->string('nasidentifier', 64)->charset('utf8')->nullable();
            $table->string('nasipaddress', 15)->charset('utf8')->nullable()->index();
            $table->string('nasporttype', 32)->charset('utf8')->nullable();
            $table->dateTime('acctstarttime')->nullable();
            $table->dateTime('acctupdatetime')->nullable();
            $table->dateTime('acctstoptime')->nullable();
            $table->unsignedInteger('acctinterval')->nullable();
            $table->unsignedInteger('acctsessiontime')->nullable();
            $table->string('acctauthentic', 32)->charset('utf8')->nullable();
            $table->string('connectinfo_start', 50)->charset('utf8')->nullable();
            $table->string('connectinfo_stop', 50)->charset('utf8')->nullable();
            $table->unsignedBigInteger('inputoctets')->nullable();
            $table->unsignedBigInteger('outputoctets')->nullable();
            $table->unsignedInteger('latency')->nullable();
            $table->unsignedInteger('packets_input')->nullable();
            $table->unsignedInteger('packets_output')->nullable();
            $table->string('sessionid', 64)->charset('utf8')->nullable();
            $table->string('calledstationid', 50)->charset('utf8')->nullable();
            $table->string('callingstationid', 50)->charset('utf8')->nullable();
            $table->string('acctterminatecause', 32)->charset('utf8')->nullable();
            $table->string('servicetype', 32)->charset('utf8')->nullable();
            $table->string('xascendsessionsvrkey', 32)->charset('utf8')->nullable();
            $table->string('xascendcurrentres', 32)->charset('utf8')->nullable();
            $table->string('xascendmultirate', 32)->charset('utf8')->nullable();
            $table->string('framedipaddress', 15)->charset('utf8')->nullable();
            $table->string('framedipv6address', 45)->charset('utf8')->nullable();
            $table->string('framedipv6prefix', 45)->charset('utf8')->nullable();
            $table->string('framedinterfaceid', 44)->charset('utf8')->nullable();
            $table->string(' delegatedipv6prefix', 45)->charset('utf8')->nullable();
            $table->index(['username', 'acctsessionid'], 'radacct_username_session_idx');
            $table->index(['acctstarttime', 'acctstoptime'], 'radacct_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radacct');
        Schema::dropIfExists('radusergroup');
        Schema::dropIfExists('radreply');
        Schema::dropIfExists('radcheck');
    }
};
