<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Common columns: uuid pk (client-generatable), tenant, version for sync, meta for custom fields. */
    private function make(string $name, callable $fn, bool $_soft = true, bool $_property = true): void
    {
        Schema::create($name, function (Blueprint $t) use ($fn) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->uuid('property_id')->nullable()->index();
            $fn($t);
            $t->json('meta')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->softDeletes(); // tombstones for sync; ledgers are voided, never hard-deleted
            $t->index(['tenant_id', 'updated_at']);
        });
    }

    public function up(): void
    {
        // ---------------- Guests & customers ----------------
        $this->make('guests', function (Blueprint $t) {
            $t->string('name');
            $t->string('phone')->nullable()->index();
            $t->string('email')->nullable();
            $t->string('nationality')->nullable();
            $t->string('id_type')->nullable();
            $t->string('id_number')->nullable();
            $t->string('id_photo_path')->nullable();
            $t->date('dob')->nullable();
            $t->text('notes')->nullable();
        }, true, false);

        // ---------------- Catalog (menu, products, services, tours, rentals...) ----------------
        $this->make('catalog_categories', function (Blueprint $t) {
            $t->string('name');
            $t->string('kind')->default('menu');
            $t->uuid('parent_id')->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_active')->default(true);
        });

        $this->make('catalog_items', function (Blueprint $t) {
            $t->uuid('category_id')->nullable()->index();
            $t->string('kind')->default('menu'); // menu|drink|product|service|tour|activity|transfer|rental|package|spa|event|other
            $t->string('name');
            $t->json('name_i18n')->nullable();
            $t->text('description')->nullable();
            $t->string('sku')->nullable();
            $t->string('barcode')->nullable()->index();
            $t->unsignedBigInteger('price')->default(0);
            $t->unsignedBigInteger('cost')->default(0);
            $t->string('pricing_model')->default('flat'); // flat|per_person|per_group|per_day|per_hour|per_night
            $t->json('tax_rate_ids')->nullable();
            $t->boolean('track_stock')->default(false);
            $t->string('station')->default('none'); // kitchen|bar|none
            $t->json('options')->nullable();   // modifiers / variants / tiered pricing
            $t->string('image_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->index(['tenant_id', 'kind']);
        });

        // ---------------- Rooms ----------------
        $this->make('room_types', function (Blueprint $t) {
            $t->string('name');
            $t->string('code')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('base_price')->default(0);
            $t->unsignedSmallInteger('max_adults')->default(2);
            $t->unsignedSmallInteger('max_children')->default(0);
            $t->json('amenities')->nullable();
            $t->boolean('is_active')->default(true);
        });

        $this->make('rooms', function (Blueprint $t) {
            $t->uuid('room_type_id')->index();
            $t->string('number');
            $t->string('floor')->nullable();
            $t->string('housekeeping_status')->default('clean'); // clean|dirty|inspected|out_of_order
            $t->boolean('is_active')->default(true);
            $t->unique(['tenant_id', 'property_id', 'number']);
        });

        $this->make('rate_plans', function (Blueprint $t) {
            $t->uuid('room_type_id')->nullable()->index();
            $t->string('name');
            $t->unsignedBigInteger('price');
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->json('days_of_week')->nullable(); // [1..7] ISO
            $t->unsignedSmallInteger('min_nights')->default(1);
            $t->string('meal_plan')->nullable(); // RO|BB|HB|FB|AI
            $t->integer('priority')->default(0);
            $t->boolean('is_active')->default(true);
        });

        $this->make('reservations', function (Blueprint $t) {
            $t->string('number');
            $t->uuid('guest_id')->nullable()->index();
            $t->uuid('room_type_id')->nullable()->index();
            $t->uuid('room_id')->nullable()->index();
            $t->uuid('rate_plan_id')->nullable();
            $t->date('check_in')->index();
            $t->date('check_out')->index();
            $t->unsignedSmallInteger('adults')->default(1);
            $t->unsignedSmallInteger('children')->default(0);
            $t->string('status')->default('confirmed'); // pending|confirmed|checked_in|checked_out|cancelled|no_show
            $t->string('source')->default('walk_in');
            $t->unsignedBigInteger('total')->default(0);
            $t->unsignedBigInteger('deposit')->default(0);
            $t->text('notes')->nullable();
            $t->timestamp('checked_in_at')->nullable();
            $t->timestamp('checked_out_at')->nullable();
            $t->unique(['tenant_id', 'number']);
        });

        // ---------------- Folios ----------------
        $this->make('folios', function (Blueprint $t) {
            $t->string('number');
            $t->uuid('reservation_id')->nullable()->index();
            $t->uuid('booking_id')->nullable()->index();
            $t->uuid('guest_id')->nullable();
            $t->string('label')->nullable();
            $t->string('status')->default('open'); // open|closed
            $t->unique(['tenant_id', 'number']);
        });

        $this->make('folio_entries', function (Blueprint $t) {
            $t->uuid('folio_id')->index();
            $t->string('type'); // charge|payment|tax|adjustment|discount
            $t->string('description');
            $t->decimal('quantity', 10, 2)->default(1);
            $t->bigInteger('unit_price')->default(0);
            $t->bigInteger('amount'); // signed: charges +, payments -
            $t->string('source_type')->nullable();
            $t->string('source_id')->nullable();
            $t->date('business_date')->nullable();
            $t->string('idempotency_key')->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->string('void_reason')->nullable();
            $t->uuid('posted_by')->nullable();
            $t->unique(['tenant_id', 'idempotency_key']);
        }, false, false);

        // ---------------- POS ----------------
        $this->make('dining_tables', function (Blueprint $t) {
            $t->string('name');
            $t->string('section')->nullable();
            $t->unsignedSmallInteger('capacity')->default(4);
            $t->integer('x')->default(0);
            $t->integer('y')->default(0);
            $t->boolean('is_active')->default(true);
        });

        $this->make('shifts', function (Blueprint $t) {
            $t->uuid('user_id')->index();
            $t->uuid('device_id')->nullable();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->bigInteger('opening_float')->default(0);
            $t->bigInteger('expected_cash')->default(0);
            $t->bigInteger('counted_cash')->nullable();
            $t->bigInteger('variance')->nullable();
            $t->string('status')->default('open');
        }, false);

        $this->make('orders', function (Blueprint $t) {
            $t->string('number');
            $t->string('type')->default('dine_in'); // dine_in|takeaway|room_service|retail|delivery
            $t->uuid('table_id')->nullable();
            $t->uuid('shift_id')->nullable();
            $t->uuid('folio_id')->nullable();
            $t->uuid('guest_id')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('status')->default('open'); // open|sent|paid|void
            $t->unsignedInteger('guests')->default(1);
            $t->bigInteger('subtotal')->default(0);
            $t->bigInteger('tax')->default(0);
            $t->bigInteger('service')->default(0);
            $t->bigInteger('discount')->default(0);
            $t->bigInteger('tip')->default(0);
            $t->bigInteger('total')->default(0);
            $t->bigInteger('paid')->default(0);
            $t->uuid('opened_by')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->string('void_reason')->nullable();
            $t->unique(['tenant_id', 'number']);
        });

        $this->make('order_items', function (Blueprint $t) {
            $t->uuid('order_id')->index();
            $t->uuid('catalog_item_id')->nullable();
            $t->string('name');
            $t->decimal('quantity', 10, 2)->default(1);
            $t->unsignedBigInteger('unit_price');
            $t->json('modifiers')->nullable();
            $t->string('notes')->nullable();
            $t->string('station')->default('none');
            $t->string('status')->default('new'); // new|sent|preparing|ready|served|void
            $t->unsignedInteger('tax_bp')->default(0);
            $t->boolean('tax_inclusive')->default(false);
            $t->bigInteger('line_total')->default(0);
            $t->timestamp('voided_at')->nullable();
            $t->string('void_reason')->nullable();
        }, false, false);

        $this->make('payments', function (Blueprint $t) {
            $t->uuid('order_id')->nullable()->index();
            $t->uuid('folio_id')->nullable()->index();
            $t->uuid('booking_id')->nullable()->index();
            $t->uuid('method_id')->nullable();
            $t->string('method_type'); // cash|momo|card|bank|room_charge|voucher|other
            $t->unsignedBigInteger('amount');
            $t->string('reference')->nullable();
            $t->string('payer_phone')->nullable();
            $t->string('status')->default('successful'); // pending|successful|failed|void
            $t->json('provider')->nullable();
            $t->uuid('received_by')->nullable();
            $t->uuid('shift_id')->nullable();
            $t->string('idempotency_key')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->unique(['tenant_id', 'idempotency_key']);
        }, false);

        // ---------------- Tourism ----------------
        $this->make('tourism_resources', function (Blueprint $t) {
            $t->string('kind'); // vehicle|guide|driver|boat|equipment|other
            $t->string('name');
            $t->unsignedSmallInteger('capacity')->nullable();
            $t->string('plate')->nullable();
            $t->string('phone')->nullable();
            $t->uuid('user_id')->nullable();
            $t->unsignedBigInteger('cost_per_day')->default(0);
            $t->boolean('is_active')->default(true);
        });

        $this->make('tour_departures', function (Blueprint $t) {
            $t->uuid('catalog_item_id')->index();
            $t->date('date')->index();
            $t->string('start_time')->nullable();
            $t->unsignedInteger('capacity');
            $t->unsignedBigInteger('price_override')->nullable();
            $t->string('status')->default('open'); // open|closed|cancelled|completed
            $t->string('meeting_point')->nullable();
        });

        $this->make('bookings', function (Blueprint $t) {
            $t->string('number');
            $t->uuid('guest_id')->nullable();
            $t->string('customer_name');
            $t->string('customer_phone')->nullable();
            $t->string('customer_email')->nullable();
            $t->uuid('catalog_item_id')->nullable()->index();
            $t->uuid('departure_id')->nullable()->index();
            $t->date('date')->index();
            $t->string('start_time')->nullable();
            $t->unsignedSmallInteger('pax_adults')->default(1);
            $t->unsignedSmallInteger('pax_children')->default(0);
            $t->string('status')->default('confirmed'); // inquiry|confirmed|paid|in_progress|completed|cancelled|no_show
            $t->string('source')->default('walk_in');
            $t->bigInteger('total')->default(0);
            $t->bigInteger('deposit')->default(0);
            $t->string('pickup_location')->nullable();
            $t->text('notes')->nullable();
            $t->uuid('reservation_id')->nullable(); // link to a hotel stay
            $t->unique(['tenant_id', 'number']);
        });

        $this->make('booking_lines', function (Blueprint $t) {
            $t->uuid('booking_id')->index();
            $t->uuid('catalog_item_id')->nullable();
            $t->string('description');
            $t->decimal('quantity', 10, 2)->default(1);
            $t->unsignedBigInteger('unit_price');
            $t->bigInteger('line_total');
        }, false, false);

        $this->make('booking_participants', function (Blueprint $t) {
            $t->uuid('booking_id')->index();
            $t->string('name');
            $t->string('nationality')->nullable();
            $t->string('id_number')->nullable();
            $t->unsignedSmallInteger('age')->nullable();
        }, false, false);

        $this->make('booking_assignments', function (Blueprint $t) {
            $t->uuid('booking_id')->index();
            $t->uuid('resource_id')->index();
            $t->string('role')->nullable();
            $t->date('date')->index();
        }, false, false);

        // ---------------- Inventory ----------------
        $this->make('suppliers', function (Blueprint $t) {
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
        });

        $this->make('inventory_items', function (Blueprint $t) {
            $t->string('name');
            $t->string('sku')->nullable();
            $t->string('unit')->default('pcs');
            $t->decimal('reorder_level', 12, 3)->default(0);
            $t->unsignedBigInteger('cost')->default(0);
            $t->uuid('catalog_item_id')->nullable()->index();
            $t->uuid('supplier_id')->nullable();
            $t->boolean('is_active')->default(true);
        });

        // Append-only ledger: quantities are never edited directly.
        $this->make('stock_movements', function (Blueprint $t) {
            $t->uuid('inventory_item_id')->index();
            $t->decimal('quantity', 14, 3); // signed
            $t->string('type'); // purchase|sale|adjustment|waste|stocktake|transfer_in|transfer_out|return
            $t->unsignedBigInteger('unit_cost')->default(0);
            $t->string('ref_type')->nullable();
            $t->string('ref_id')->nullable();
            $t->string('note')->nullable();
            $t->uuid('user_id')->nullable();
            $t->string('idempotency_key')->nullable();
            $t->unique(['tenant_id', 'idempotency_key']);
        }, false);

        $this->make('recipes', function (Blueprint $t) {
            $t->uuid('catalog_item_id')->index();
            $t->uuid('inventory_item_id')->index();
            $t->decimal('quantity', 12, 3);
        }, false, false);

        $this->make('purchase_orders', function (Blueprint $t) {
            $t->string('number');
            $t->uuid('supplier_id')->nullable();
            $t->string('status')->default('draft'); // draft|ordered|received|cancelled
            $t->json('lines'); // [{inventory_item_id, quantity, unit_cost, received}]
            $t->bigInteger('total')->default(0);
            $t->date('expected_on')->nullable();
            $t->unique(['tenant_id', 'number']);
        });

        // ---------------- Staff ----------------
        $this->make('employees', function (Blueprint $t) {
            $t->uuid('user_id')->nullable();
            $t->string('name');
            $t->string('position')->nullable();
            $t->string('phone')->nullable();
            $t->string('national_id')->nullable();
            $t->date('hired_on')->nullable();
            $t->string('pay_type')->default('monthly'); // monthly|daily|hourly
            $t->unsignedBigInteger('pay_rate')->default(0);
            $t->boolean('is_active')->default(true);
        });

        $this->make('roster_shifts', function (Blueprint $t) {
            $t->uuid('employee_id')->index();
            $t->date('date')->index();
            $t->string('start_time');
            $t->string('end_time');
            $t->string('label')->nullable();
        }, false);

        $this->make('attendance_events', function (Blueprint $t) {
            $t->uuid('employee_id')->index();
            $t->string('type'); // in|out
            $t->timestamp('at');
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->uuid('device_id')->nullable();
            $t->boolean('flagged')->default(false);
        }, false);

        $this->make('leave_requests', function (Blueprint $t) {
            $t->uuid('employee_id')->index();
            $t->string('type')->default('annual');
            $t->date('from_date');
            $t->date('to_date');
            $t->string('status')->default('pending'); // pending|approved|rejected
            $t->string('reason')->nullable();
            $t->uuid('decided_by')->nullable();
        });

        // ---------------- Housekeeping & maintenance ----------------
        $this->make('work_tasks', function (Blueprint $t) {
            $t->string('type'); // clean|inspect|turndown|maintenance|other
            $t->uuid('room_id')->nullable()->index();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('status')->default('pending'); // pending|in_progress|done|inspected
            $t->string('priority')->default('normal'); // low|normal|high|urgent
            $t->uuid('assigned_to')->nullable()->index();
            $t->json('photos')->nullable();
            $t->timestamp('due_at')->nullable();
            $t->timestamp('completed_at')->nullable();
        });

        // ---------------- Finance ----------------
        $this->make('expenses', function (Blueprint $t) {
            $t->string('category');
            $t->string('description')->nullable();
            $t->unsignedBigInteger('amount');
            $t->date('date')->index();
            $t->string('paid_with')->default('cash');
            $t->uuid('supplier_id')->nullable();
            $t->uuid('recorded_by')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'work_tasks', 'leave_requests', 'attendance_events', 'roster_shifts', 'employees', 'purchase_orders',
            'recipes', 'stock_movements', 'inventory_items', 'suppliers', 'booking_assignments', 'booking_participants',
            'booking_lines', 'bookings', 'tour_departures', 'tourism_resources', 'payments', 'order_items', 'orders',
            'shifts', 'dining_tables', 'folio_entries', 'folios', 'reservations', 'rate_plans', 'rooms', 'room_types',
            'catalog_items', 'catalog_categories', 'guests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
