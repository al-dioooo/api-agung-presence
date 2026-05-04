<?php

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(Office::class)->constrained();

            $table->date('date')->useCurrent();

            $table->datetime('in_at')->useCurrent();
            $table->datetime('out_at')->nullable();

            $table->decimal('in_latitude', 10, 8);
            $table->decimal('in_longitude', 11, 8);

            $table->text('proof_photo')->nullable();

            $table->string('status', 25)->default('on_time');

            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
