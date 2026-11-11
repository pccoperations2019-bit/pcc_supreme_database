# Data Modelling: Production of Milk

## Description

The current milk production data model uses a single denormalized table `TBL_MILK_PRODUCTION` that does not effectively map the flow of milk production processes. This ticket addresses the need to create a normalized data model that captures various aspects of milk production including sources, usage, sales, wastage, and operational costs.

The existing table combines production data, sales information, usage metrics, and cost details in a single structure, making it difficult to maintain data integrity, generate accurate reports, and track the milk production workflow effectively.

This implementation will establish a foundation for better data warehousing and business intelligence capabilities, directly supporting PCC's mission to monitor and evaluate their carabao-based enterprise development programs.

## Requirements

### Functional Requirements

1. **Normalize the existing denormalized milk production table** into multiple related tables following database normalization principles
2. **Implement a status pipeline** for milk production records: Draft → Submitted → Approved → Rejected → Completed
3. **Create multi-sheet Excel import functionality** to support bulk data upload during Phase 1
4. **Maintain data relationships** between centers, enterprises, farmers, and production records
5. **Support calculated fields** such as "Average Milk per Animal per Day" through database views or model accessors
6. **Preserve existing data mappings** while improving the underlying structure

### Non-Functional Requirements

1. **Data Integrity**: Enforce referential integrity between related tables
2. **Performance**: Support efficient querying for reporting and analytics
3. **Scalability**: Design to handle increasing volumes of production data
4. **Auditability**: Track changes and status transitions for compliance

### Acceptance Criteria

- [ ] Five normalized tables are created: `milk_productions`, `milk_sales`, `milk_usage`, `milk_wastage`, `milk_costs`
- [ ] All existing data fields from `TBL_MILK_PRODUCTION` are mapped to appropriate normalized tables
- [ ] Status pipeline is implemented with proper state transitions and validation
- [ ] Multi-sheet Excel import template is created and functional
- [ ] Database migrations preserve existing data during transition
- [ ] Unit tests cover all new models and relationships
- [ ] Documentation is updated with new data model relationships

## Technical Specifications

### Database Schema Design

#### 1. Core Production Table (`milk_productions`)
```php
Schema::create('milk_productions', function (Blueprint $table) {
    $table->id();
    $table->string('production_code', 20)->unique(); // CEN-ENT-MPROD001
    $table->foreignId('center_id')->constrained();
    $table->foreignId('partner_id')->constrained(); // Enterprise/Partner
    $table->enum('production_type', ['MILK_PRODUCTION']);
    $table->date('transaction_date');
    $table->integer('year');
    $table->integer('month');
    $table->integer('week');
    $table->string('duration', 50)->nullable(); // Jan 10-16
    $table->decimal('total_volume', 10, 2);
    $table->decimal('volume_from_farmers', 10, 2)->nullable();
    $table->enum('milking_times', ['AM', 'PM', 'AM_PM']);
    $table->integer('number_of_lactating');
    $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'completed'])->default('draft');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

#### 2. Sales Table (`milk_sales`)
```php
Schema::create('milk_sales', function (Blueprint $table) {
    $table->id();
    $table->foreignId('milk_production_id')->constrained();
    $table->decimal('milk_traded_kgs', 10, 2);
    $table->decimal('farm_gate_price', 8, 2);
    $table->decimal('raw_milk_traded_value', 12, 2); // Calculated: milk_traded * farm_gate_price
    // need to confirm this. 
    $table->decimal('value_added_products_sales', 12, 2)
        ->comment('actual sales from value added products')
        ->nullable();
    $table->decimal('dairy_box_sales', 12, 2)->nullable();
    $table->timestamps();
});
```

#### 3. Usage Table (`milk_usage`)
```php
Schema::create('milk_usage', function (Blueprint $table) {
    $table->id();
    $table->foreignId('milk_production_id')->constrained();
    $table->enum('usage_type', ['FED_TO_CALVES', 'FAMILY_CONSUMPTION', 'MILK_FEEDING_PROGRAM']);
    $table->decimal('quantity_kgs', 10, 2);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

#### 4. Wastage Table (`milk_wastage`)
```php
Schema::create('milk_wastage', function (Blueprint $table) {
    $table->id();
    $table->foreignId('milk_production_id')->constrained();
    $table->decimal('spoiled_quantity_kgs', 10, 2);
    $table->string('reason')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

#### 5. Costs Table (`milk_costs`)
```php
Schema::create('milk_costs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('milk_production_id')->constrained();
    $table->decimal('raw_milk_buying_price', 8, 2)->nullable();
    $table->integer('staff_male_count')->default(0);
    $table->integer('staff_female_count')->default(0);
    $table->decimal('average_monthly_salary', 10, 2)->nullable();
    $table->timestamps();
});
```

### Model Relationships

```php
// MilkProduction Model
class MilkProduction extends Model
{
    public function center() { return $this->belongsTo(Center::class); }
    public function partner() { return $this->belongsTo(Partner::class); }
    public function sales() { return $this->hasMany(MilkSale::class); }
    public function usage() { return $this->hasMany(MilkUsage::class); }
    public function wastage() { return $this->hasMany(MilkWastage::class); }
    public function costs() { return $this->hasMany(MilkCost::class); }
    
    // Calculated attribute
    public function getAverageMilkPerAnimalPerDayAttribute()
    {
        $milkingMultiplier = $this->milking_times === 'AM_PM' ? 2 : 1;
        return ($this->number_of_lactating > 0) 
            ? ($this->total_volume / 20 / $this->number_of_lactating / $milkingMultiplier)
            : 0;
    }
}
```

### Excel Import Structure

#### Multi-Sheet Template:
1. **Production Sheet**: Core production data
2. **Sales Sheet**: Sales and trading information
3. **Usage Sheet**: Internal usage breakdown
4. **Wastage Sheet**: Spoilage and loss data
5. **Costs Sheet**: Operational costs

#### Import Implementation:
```php
class MilkProductionImport implements ToCollection, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Production' => new MilkProductionSheetImport(),
            'Sales' => new MilkSalesSheetImport(),
            'Usage' => new MilkUsageSheetImport(),
            'Wastage' => new MilkWastageSheetImport(),
            'Costs' => new MilkCostsSheetImport(),
        ];
    }
}
```

### Status Workflow

- **Draft**: Initial creation, editable
- **Submitted**: Ready for review, read-only for creators
- **Approved**: Validated and accepted
- **Rejected**: Requires revision, returns to Draft
- **Completed**: Final processing completed

## Risk Assessment

### Technical Risks
- **Data Migration Complexity**: Converting existing denormalized data to normalized structure may require complex transformation logic
- **Performance Impact**: Multiple table joins might affect query performance for large datasets
- **Import Validation**: Multi-sheet Excel imports require robust validation to prevent data corruption

### Mitigation Strategies
- Implement comprehensive data migration scripts with rollback capabilities
- Use database indexing and consider materialized views for frequently accessed aggregated data
- Create detailed validation rules with clear error messaging for import processes

### Dependencies
- Existing Center and Partner models must be stable
- Excel import functionality (Maatwebsite/Laravel-Excel) must be properly configured
- Database migration strategy must be approved by DBA team

## Additional Notes

### References
- Current table structure: `TBL_MILK_PRODUCTION` (see attachment)
- Related models: `app/Models/Center.php`, `app/Models/Partner.php`
- Import implementations: `app/Imports/`

### Future Considerations
- This normalized structure will support future reporting and analytics requirements
- Consider implementing event sourcing for production status changes
- Plan for API endpoints to support mobile data collection applications

### Development Approach
1. Create database migrations for new normalized tables
2. Develop Eloquent models with proper relationships
3. Implement multi-sheet Excel import functionality
4. Create data migration script from existing structure
5. Add comprehensive test coverage
6. Update documentation and create user guides