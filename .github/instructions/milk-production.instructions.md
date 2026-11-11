## 🧭 Overview

### Purpose

This module handles all milk production–related records, including sales, costs, wastage, and usage. It supports importing data from Excel per center and automates record creation or updates through the action classes.

---

## ⚙️ System Architecture

### 1. **Main Models**

| Model              | Description                                                   |
| ------------------ | ------------------------------------------------------------- |
| **MilkProduction** | Core record for daily/weekly milk production data per center. |
| **MilkSale**       | Sales and revenue information related to milk output.         |
| **MilkCost**       | Operational expenses and labor data.                          |
| **MilkUsage**      | Milk consumption for household, calves, or other purposes.    |
| **MilkWastage**    | Records spoiled or wasted milk quantities.                    |

---

## 🧩 Database Structure

### **milk_productions**

```php
$table->id();
$table->string('production_code')->comment('Unique code per production batch');
$table->foreignId('center_id')->constrained()->comment('Reference to milk center');
$table->foreignId('partner_id')->constrained()->comment('Associated farmer/partner');
$table->string('production_type')->default(ProductionTypeEnum::DEFAULT->value)->comment('Daily, Weekly, Monthly, etc.');
$table->date('transaction_date')->comment('Production date');
$table->integer('year')->comment('Extracted year');
$table->integer('month')->comment('Extracted month number');
$table->integer('week')->comment('Extracted week number');
$table->string('duration', 50)->nullable()->comment('Range of dates, e.g., Jan 10–16');
$table->decimal('total_volume', 10, 2)->default(0)->comment('Total milk produced');
$table->decimal('volume_from_farmers', 10, 2)->default(0)->comment('Milk sourced from other farmers');
$table->string('milking_times')->default(MilkingTimeEnum::DEFAULT->value)->comment('Morning, Evening, or Both');
$table->integer('number_of_lactating')->default(0)->comment('Number of lactating animals');
$table->decimal('avg_milk_per_animal_per_day', 10, 2)->default(0)->comment('Average milk yield per animal');
$table->string('status')->default(ProductionStatusEnum::DEFAULT->value)->comment('Record status: draft, confirmed, etc.');
$table->text('notes')->nullable()->comment('Additional remarks');
$table->timestamps();
$table->softDeletes();
```

---

### **milk_sales**

```php
$table->id();
$table->foreignId('milk_production_id')->constrained()->cascadeOnDelete()->comment('Linked production record');
$table->decimal('milk_traded_kgs', 10, 2)->comment('Total traded milk volume');
$table->decimal('farm_gate_price', 8, 2)->comment('Price per kg');
$table->decimal('raw_milk_traded_value', 12, 2)->comment('milk_traded_kgs * farm_gate_price');
$table->decimal('value_added_products_sales', 12, 2)->nullable()->comment('Sales from processed milk products');
$table->decimal('dairy_box_sales', 12, 2)->nullable()->comment('Sales through dairy box outlets');
$table->decimal('milk_sales', 12, 2)->nullable()->comment('Total milk sales');
$table->decimal('milk_feeding_sales', 12, 2)->nullable()->comment('Sales from milk feeding initiatives');
$table->timestamps();
```

---

### **milk_costs**

```php
$table->id();
$table->foreignId('milk_production_id')->constrained()->cascadeOnDelete()->comment('Linked production record');
$table->decimal('raw_milk_buying_price', 8, 2)->nullable()->comment('Cost of purchased milk');
$table->integer('staff_male_count')->default(0)->comment('No. of male staff');
$table->integer('staff_female_count')->default(0)->comment('No. of female staff');
$table->decimal('average_monthly_salary', 10, 2)->nullable()->comment('Average salary of staff');
$table->timestamps();
```

---

### **milk_usages**

```php
$table->id();
$table->foreignId('milk_production_id')->constrained()->cascadeOnDelete()->comment('Linked production record');
$table->string('usage_type')->default(MilkUsageTypeEnum::DEFAULT->value)->comment('Type of usage: Family, Calves, etc.');
$table->decimal('quantity_kgs', 10, 2)->comment('Amount of milk used');
$table->text('notes')->nullable()->comment('Remarks or additional info');
$table->timestamps();
```

---

### **milk_wastages**

```php
$table->id();
$table->foreignId('milk_production_id')->constrained()->cascadeOnDelete()->comment('Linked production record');
$table->decimal('spoiled_quantity_kgs', 10, 2)->comment('Total spoiled milk volume');
$table->string('reason')->nullable()->comment('Cause of wastage');
$table->text('notes')->nullable()->comment('Remarks');
$table->timestamps();
```

---

## ⚙️ Core Actions

| Action                       | Purpose                                           |
| ---------------------------- | ------------------------------------------------- |
| `CreateMilkProductionAction` | Creates a milk production record.                 |
| `UpsertMilkProductionAction` | Updates or inserts production entry by code/date. |
| `CreateMilkSaleAction`       | Creates sales record for a production.            |
| `CreateMilkCostAction`       | Creates cost record for a production.             |
| `CreateMilkUsageAction`      | Creates usage record for a production.            |
| `CreateMilkWastageAction`    | Creates wastage record for a production.          |

Each action encapsulates business logic — keeping imports and controllers clean.

---

## 📥 Excel Imports

### 1. `MilkProductionImport`

Handles importing all milk production data from Excel. It delegates specific sheet handling to other sheet importers.

### 2. `MilkProductionByCenterImport`

Imports data for a specific center. It links all child records (sales, cost, wastage, usage) to the same `center_id`.

### 3. `ProductionSheetImport`

Processes the **main production sheet** (partner code, production code, transaction date, total volume, etc.).

### 4. `SalesSheetImport`

Handles all milk sales data:

```php
resolve(CreateMilkSaleAction::class)->execute(
  milkProductionId: $milkProductionId,
  milkTradedKgs: $parse($data['milk_traded_kgs']),
  farmGatePrice: $parse($data['farm_gate_price']),
  rawMilkTradedValue: $parse($data['raw_milk_traded_value']),
  valueAddedProductsSales: $parse($data['value_added_products_sales']),
  dairyBoxSales: $parse($data['dairy_box_sales']),
  milkSales: $parse($data['milk_sales']),
  milkFeedingSales: $parse($data['milk_feeding_sales'])
);
```

### 5. `CostsSheetImport`

Handles operational expenses, staff counts, and salaries.

### 6. `UsageSheetImport`

Handles milk consumption or usage data:

- Family consumption
- Calf feeding
- Other uses

### 7. `WastageSheetImport`

Handles spoiled milk entries and reasons for spoilage.

---

## 🧑‍💼 User Guide

### 📊 Importing Data

1. Go to **Milk Production → Import Data** in your admin panel.
2. Upload the Excel file containing all required sheets:
   - `Production`
   - `Sales`
   - `Costs`
   - `Usage`
   - `Wastage`

3. The system automatically detects sheet names and processes them in chunks of **500 rows** for performance.

### 🧾 Viewing Data

- Navigate to **Milk Production → Records**.
- Each record shows:
  - Partner
  - Production Date
  - Total Milk Volume
  - Linked Sales, Costs, Usage, and Wastage Data.

- You can drill down to view detailed transactions per type.

### ⚠️ Data Validation

- Rows without a **production_code** or **date** are skipped.
- Numeric values are auto-converted to `float(2)`.
- Missing production records auto-create new `MilkProduction` entries.

### 🧰 Admin Tips

- Keep Excel headers consistent with your sheet importers (case-insensitive).
- Use CSV/Excel format exported from your system template.

---

## 🧠 Developer Notes

- **Enums Used**:
  - `ProductionTypeEnum`
  - `MilkingTimeEnum`
  - `ProductionStatusEnum`
  - `MilkUsageTypeEnum`

- **Relationships**:

  ```php
  MilkProduction hasMany MilkSale
  MilkProduction hasMany MilkCost
  MilkProduction hasMany MilkUsage
  MilkProduction hasMany MilkWastage
  ```

- **Chunk Import Performance**:
  Each sheet uses `WithChunkReading` → chunk size `500` for efficient memory handling.
