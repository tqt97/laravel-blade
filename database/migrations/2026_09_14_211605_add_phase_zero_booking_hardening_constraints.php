<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('reserved_booking_id')->nullable()
                ->storedAs("CASE WHEN status = 'reserved' THEN booking_id ELSE NULL END");
            $table->unique('reserved_booking_id', 'coupon_reservations_one_reserved_booking_unique');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            foreach ([
                ['bookings', 'amount_minor_units >= 0 AND subtotal_minor_units >= 0 AND discount_minor_units >= 0 AND total_minor_units >= 0', 'bookings_amounts_non_negative'],
                ['bookings', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND') AND pricing_currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'bookings_currency_valid'],
                ['screenings', 'ends_at > starts_at AND base_price_minor_units >= 0', 'screenings_values_valid'],
                ['screenings', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'screenings_currency_valid'],
                ['screening_seats', 'price_minor_units >= 0', 'screening_seats_price_non_negative'],
                ['screening_seats', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'screening_seats_currency_valid'],
                ['booking_items', 'price_minor_units >= 0', 'booking_items_price_non_negative'],
                ['booking_items', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'booking_items_currency_valid'],
                ['booking_concessions', 'quantity >= 0 AND unit_price_minor_units >= 0 AND total_minor_units >= 0', 'booking_concessions_values_valid'],
                ['booking_concessions', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'booking_concessions_currency_valid'],
                ['concessions', 'price_minor_units >= 0 AND (stock IS NULL OR stock >= 0)', 'concessions_values_valid'],
                ['coupons', 'value >= 0 AND (maximum_discount_minor_units IS NULL OR maximum_discount_minor_units >= 0) AND reserved_count >= 0 AND redeemed_count >= 0 AND used_count >= 0', 'coupons_values_valid'],
                ['coupons', "currency IS NULL OR currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'coupons_currency_valid'],
                ['concessions', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'concessions_currency_valid'],
                ['concession_inventory_movements', 'stock_before IS NULL OR stock_before >= 0', 'inventory_stock_before_non_negative'],
                ['concession_inventory_movements', 'stock_after IS NULL OR stock_after >= 0', 'inventory_stock_after_non_negative'],
                ['payments', 'amount_minor_units >= 0', 'payments_amount_non_negative'],
                ['payments', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'payments_currency_valid'],
                ['payment_attempts', 'amount_minor_units >= 0', 'payment_attempts_amount_non_negative'],
                ['payment_attempts', "currency IN ('EUR','GBP','JPY','SGD','THB','USD','VND')", 'payment_attempts_currency_valid'],
            ] as [$table, $expression, $constraint]) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
            }
        } else {
            $this->createSqliteInvariantTriggers();
        }

        $this->createBookingItemScreeningTriggers();
    }

    public function down(): void
    {
        $this->dropBookingItemScreeningTriggers();

        Schema::table('coupon_reservations', function (Blueprint $table): void {
            $table->dropUnique('coupon_reservations_one_reserved_booking_unique');
            $table->dropColumn('reserved_booking_id');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            foreach ([
                ['bookings', 'bookings_amounts_non_negative'], ['bookings', 'bookings_currency_valid'],
                ['screenings', 'screenings_values_valid'], ['screenings', 'screenings_currency_valid'],
                ['screening_seats', 'screening_seats_price_non_negative'], ['screening_seats', 'screening_seats_currency_valid'],
                ['booking_items', 'booking_items_price_non_negative'], ['booking_items', 'booking_items_currency_valid'],
                ['booking_concessions', 'booking_concessions_values_valid'], ['booking_concessions', 'booking_concessions_currency_valid'],
                ['concessions', 'concessions_values_valid'], ['concessions', 'concessions_currency_valid'],
                ['coupons', 'coupons_values_valid'], ['coupons', 'coupons_currency_valid'],
                ['concession_inventory_movements', 'inventory_stock_before_non_negative'], ['concession_inventory_movements', 'inventory_stock_after_non_negative'],
                ['payments', 'payments_amount_non_negative'], ['payments', 'payments_currency_valid'],
                ['payment_attempts', 'payment_attempts_amount_non_negative'], ['payment_attempts', 'payment_attempts_currency_valid'],
            ] as [$table, $constraint]) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
            }
        } else {
            foreach ($this->sqliteInvariantTables() as $table => $_expression) {
                DB::unprepared("DROP TRIGGER IF EXISTS phase_zero_{$table}_insert");
                DB::unprepared("DROP TRIGGER IF EXISTS phase_zero_{$table}_update");
            }
        }
    }

    private function createBookingItemScreeningTriggers(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER booking_items_screening_match_insert BEFORE INSERT ON booking_items BEGIN SELECT RAISE(ABORT, 'booking item screening mismatch') WHERE (SELECT screening_id FROM bookings WHERE id = NEW.booking_id) != (SELECT screening_id FROM screening_seats WHERE id = NEW.screening_seat_id); END");
            DB::unprepared("CREATE TRIGGER booking_items_screening_match_update BEFORE UPDATE OF booking_id, screening_seat_id ON booking_items BEGIN SELECT RAISE(ABORT, 'booking item screening mismatch') WHERE (SELECT screening_id FROM bookings WHERE id = NEW.booking_id) != (SELECT screening_id FROM screening_seats WHERE id = NEW.screening_seat_id); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER booking_items_screening_match_insert BEFORE INSERT ON booking_items FOR EACH ROW BEGIN IF (SELECT screening_id FROM bookings WHERE id = NEW.booking_id) <> (SELECT screening_id FROM screening_seats WHERE id = NEW.screening_seat_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'booking item screening mismatch'; END IF; END");
        DB::unprepared("CREATE TRIGGER booking_items_screening_match_update BEFORE UPDATE ON booking_items FOR EACH ROW BEGIN IF (SELECT screening_id FROM bookings WHERE id = NEW.booking_id) <> (SELECT screening_id FROM screening_seats WHERE id = NEW.screening_seat_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'booking item screening mismatch'; END IF; END");
    }

    /** @return array<string, string> */
    private function sqliteInvariantTables(): array
    {
        return [
            'bookings' => "NEW.amount_minor_units < 0 OR NEW.subtotal_minor_units < 0 OR NEW.discount_minor_units < 0 OR NEW.total_minor_units < 0 OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND') OR NEW.pricing_currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'screenings' => "NEW.ends_at <= NEW.starts_at OR NEW.base_price_minor_units < 0 OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'screening_seats' => "NEW.price_minor_units < 0 OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'booking_items' => "NEW.price_minor_units < 0 OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'booking_concessions' => "NEW.quantity < 0 OR NEW.unit_price_minor_units < 0 OR NEW.total_minor_units < 0 OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'concessions' => "NEW.price_minor_units < 0 OR (NEW.stock IS NOT NULL AND NEW.stock < 0) OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'coupons' => "NEW.value < 0 OR (NEW.maximum_discount_minor_units IS NOT NULL AND NEW.maximum_discount_minor_units < 0) OR NEW.reserved_count < 0 OR NEW.redeemed_count < 0 OR NEW.used_count < 0 OR (NEW.currency IS NOT NULL AND NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND'))",
            'payments' => "NEW.amount_minor_units < 0 OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'payment_attempts' => "NEW.amount_minor_units < 0 OR NEW.currency NOT IN ('EUR','GBP','JPY','SGD','THB','USD','VND')",
            'concession_inventory_movements' => '(NEW.stock_before IS NOT NULL AND NEW.stock_before < 0) OR (NEW.stock_after IS NOT NULL AND NEW.stock_after < 0)',
        ];
    }

    private function createSqliteInvariantTriggers(): void
    {
        foreach ($this->sqliteInvariantTables() as $table => $expression) {
            DB::unprepared("CREATE TRIGGER phase_zero_{$table}_insert BEFORE INSERT ON {$table} BEGIN SELECT RAISE(ABORT, 'phase zero invariant violated') WHERE {$expression}; END");
            DB::unprepared("CREATE TRIGGER phase_zero_{$table}_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'phase zero invariant violated') WHERE {$expression}; END");
        }
    }

    private function dropBookingItemScreeningTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS booking_items_screening_match_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS booking_items_screening_match_update');
    }
};
