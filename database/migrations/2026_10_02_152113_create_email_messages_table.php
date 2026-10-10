<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('delivery_status');
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to');
            $table->json('cc');
            $table->string('message_id')->index();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('resend_id')->nullable()->unique();
            $table->longText('body_html')->nullable();
            $table->timestamps();
        });

        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('content_type');
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('email_messages');
    }
};
