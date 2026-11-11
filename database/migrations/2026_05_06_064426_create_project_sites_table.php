<?php

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
        Schema::create('project_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained('centers')->onDelete('set null')->comment('Reference to the center where the project site is located');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('set null')->comment('Reference to the partner who owns the project site');
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null')->comment('Reference to the project associated with the site');
            $table->foreignId('service_record_id')->nullable()->constrained('service_records')->onDelete('set null')->comment('Reference to the service record associated with the project site');
            $table->string('project_site_code')->nullable()->unique()->comment('Unique code for the project site');
            $table->string('value_chain_status')->nullable()->comment('Status of the value chain at the project site');
            $table->date('inauguration_date')->nullable()->comment('Date when the project site was inaugurated');
            $table->string('staff_name')->nullable()->comment('Name of the staff responsible for the project site');
            $table->string('staff_phone')->nullable()->comment('Phone number of the staff responsible for the project site');
            $table->string('staff_position')->nullable()->comment('Position of the staff responsible for the project site');
            $table->string('type_of_contract')->nullable()->comment('Type of Contract');

            $table->integer('year')->nullable()->comment('Year of the project site');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        // procurement of animals and other production details
        Schema::create('project_site_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to the project site');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('set null')->comment('Reference to the partner supplier of the project site');
            $table->integer('no_of_animals_to_be_delivered')->default(0)->comment('Number of Animals to be Delivered');
            $table->string('pcc_counterpart')->nullable()->comment('PCC Counterpart');
            $table->string('type_of_contract')->nullable()->comment('Type of Contract');
            $table->string('supplier')->nullable()->comment('Supplier Name');
            $table->integer('delivered')->default(0)->comment('Number of Animals Delivered');
            $table->integer('no_of_recipients')->default(0)->comment('Number of Recipients');
            $table->string('procuring_agency')->nullable()->comment('Procuring Agency');
            $table->string('mode_of_procurement')->nullable()->comment('Mode of Procurement');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_construction_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to the project site');
            $table->foreignId('dairybox_id')->nullable()->constrained('dairy_boxes')->onDelete('set null')->comment('Reference to the dairybox used for construction');
            $table->foreignId('production_id')->nullable()->constrained('project_site_productions')->onDelete('set null')->comment('Reference to the project site production details');
            $table->string('type')->nullable()->comment('Type of construction (e.g., processing infrastructure, dairybox or production site)');
            $table->date('date_of_establishment')->nullable()->comment('Date of Establishment');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_record_id')->nullable()->constrained('service_records')->onDelete('set null')->comment('Reference to the service record');
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to the project site');

            // Training fields
            $table->date('project_orientation_date')->nullable()->comment('Project Orientation Date Conducted');
            $table->integer('project_orientation_manual_pax')->default(0)->comment('Manual input for Project Orientation participants');

            $table->date('strategic_planning_date')->nullable()->comment('Strategic Planning Date Conducted');
            $table->integer('strategic_planning_manual_pax')->default(0)->comment('Manual input for Strategic Planning participants');

            $table->date('social_preparation_training_date')->nullable()->comment('Social Preparation Training Date Conducted');
            $table->integer('social_preparation_training_manual_pax')->default(0)->comment('Manual input for Social Preparation Training participants');

            $table->date('forage_production_training_date')->nullable()->comment('Forage Production, Feeding and Other Nutrition Training Date Conducted');
            $table->integer('forage_production_training_manual_pax')->default(0)->comment('Manual input for Forage Production, Feeding and Other Nutrition Training participants');

            $table->date('silage_making_training_date')->nullable()->comment('Training on Silage Making Date Conducted');
            $table->integer('silage_making_training_manual_pax')->default(0)->comment('Manual input for Training on Silage Making participants');

            $table->date('animal_health_training_date')->nullable()->comment('Training on Animal Health and Basic Buffalo Mgmt. Training Date Conducted');
            $table->integer('animal_health_training_manual_pax')->default(0)->comment('Manual input for Training on Animal Health and Basic Buffalo Mgmt. Training participants');

            $table->date('breeding_management_training_date')->nullable()->comment('Breeding Management, Bull Handlers Training Date Conducted');
            $table->integer('breeding_management_training_manual_pax')->default(0)->comment('Manual input for Breeding Management, Bull Handlers Training participants');

            $table->date('milk_handling_training_date')->nullable()->comment('Training on Hygienic Milk Handling Date Conducted');
            $table->integer('milk_handling_training_manual_pax')->default(0)->comment('Manual input for Training on Hygienic Milk Handling participants');

            $table->date('milk_processing_training_date')->nullable()->comment('Training on Milk / Product Processing Date Conducted');
            $table->integer('milk_processing_training_manual_pax')->default(0)->comment('Manual input for Training on Milk / Product Processing participants');

            $table->date('animal_husbandry_training_date')->nullable()->comment('Training on Good Animal Husbandry Practices and Dairy Safety Date Conducted');
            $table->integer('animal_husbandry_training_manual_pax')->default(0)->comment('Manual input for Training on Good Animal Husbandry Practices and Dairy Safety participants');

            $table->date('organic_fertilizer_training_date')->nullable()->comment('Training on Organic Fertilizer Production Date Conducted');
            $table->integer('organic_fertilizer_training_manual_pax')->default(0)->comment('Manual input for Training on Organic Fertilizer Production participants');

            $table->date('cooperative_management_training_date')->nullable()->comment('Cooperative Management and Governance Date Conducted');
            $table->integer('cooperative_management_training_manual_pax')->default(0)->comment('Manual input for Cooperative Management and Governance participants');

            $table->date('fls_launching_date')->nullable()->comment('FLS on Dairy Production Launching Date');
            $table->integer('fls_launching_manual_pax')->default(0)->comment('Manual input for FLS on Dairy Production Launching participants');

            $table->date('fls_buffalo_training_date')->nullable()->comment('FLS on Dairy Buffalo Production Training Date Conducted');
            $table->integer('fls_buffalo_training_manual_pax')->default(0)->comment('Manual input for FLS on Dairy Buffalo Production Training participants');

            $table->date('fls_date')->nullable()->comment('Farmers Livestock School Date');
            $table->integer('fls_manual_pax')->default(0)->comment('Manual input for Farmers Livestock School participants');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to the project site');
            $table->string('type')->nullable()->comment('Document type (MOA, DOD/USUFRUCT, Management Agreement Contract, etc.)');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('set null')->comment('Partner reference (for COOP)');
            $table->string('partner_type')->nullable()->comment('Partner type (COOP or LGU)');
            $table->string('partner_name')->nullable()->comment('Partner name (for LGU manual input)');
            $table->string('attachment')->nullable()->comment('File attachment path');
            $table->string('subject')->nullable()->comment('Document subject');
            $table->text('description')->nullable()->comment('Document description or status');
            $table->date('date')->nullable()->comment('Document date');
            $table->string('doc_type')->nullable()->comment('Document category (MOA, etc.)');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_infrastructures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to project site');
            $table->string('type_of_ownership')->nullable()->comment('Type of Ownership (e.g. Cooperative)');
            $table->date('start_of_construction')->nullable()->comment('Start of Construction date');
            $table->string('completion_status')->nullable()->comment('Completion Status (e.g. Ongoing)');
            $table->date('target_date_for_completion')->nullable()->comment('Target date for Completion');
            $table->date('date_inaugurated')->nullable()->comment('Date Inaugurated');
            $table->string('contractor')->nullable()->comment('Contractor name');
            $table->string('attachment_transfer_documents')->nullable()->comment('Attachment Transfer/Proof of ownership documents');
            $table->date('date_completed')->nullable()->comment('Date Completed');
            $table->integer('percentage_of_progress')->default(0)->comment('Percentage of Progress to Date');

            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to project site');
            $table->string('ownership_status')->nullable()->comment('Ownership status (PCC/LGU/COOP)');
            $table->string('attachment_ownership_documents')->nullable()->comment('Attachment Transfer/Proof of ownership documents');
            $table->string('supplier_name')->nullable()->comment('Supplier Name');
            $table->string('name_of_equipment')->nullable()->comment('Name of Equipment');
            $table->date('delivery_of_equipment')->nullable()->comment('Delivery of Equipment date');
            $table->date('date_installed')->nullable()->comment('Date Installed');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_budget_utilizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to project site');
            $table->integer('year')->nullable()->comment('Year');
            $table->string('month')->nullable()->comment('Month (JAN, FEB, MAR, etc.)');
            $table->decimal('budget_allocated', 15, 2)->default(0)->comment('Budget Allocated');
            $table->decimal('obligated', 15, 2)->default(0)->comment('Obligated Amount');
            $table->decimal('disbursed', 15, 2)->default(0)->comment('Disbursed Amount');
            $table->decimal('utilization_percentage', 5, 2)->default(0)->comment('Utilization Percentage');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_processing_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to project site');
            $table->string('staff_name')->nullable()->comment('Name of Staff');
            $table->string('contact_number')->nullable()->comment('Contact Number');
            $table->integer('number_of_staff')->default(0)->comment('Number of Processing Staff');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });

        Schema::create('project_site_marketing_infrastructures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to project site');
            $table->foreignId('dairy_box_id')->nullable()->constrained('dairy_boxes')->onDelete('set null')->comment('Reference to dairybox');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_site_marketing_infrastructures');
        Schema::dropIfExists('project_site_processing_staff');
        Schema::dropIfExists('project_site_budget_utilizations');
        Schema::dropIfExists('project_site_equipment');
        Schema::dropIfExists('project_site_infrastructures');
        Schema::dropIfExists('project_site_documents');
        Schema::dropIfExists('project_site_training_records');
        Schema::dropIfExists('project_site_construction_details');
        Schema::dropIfExists('project_site_productions');
        Schema::dropIfExists('project_sites');
    }
};
