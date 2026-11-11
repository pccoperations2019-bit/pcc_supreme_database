---
ISSUE ID: 18
TITLE: Data Modelling: Production of Milk #18
CATEGORIES: Feature Request, POC
---

This ticket will provide solution and modeling for recording Milk Production data in the system.

## Problem Statement

Current Database: 

`TBL_MILK_PRODUCTION`

| **Field Name**                      | **Data Type** | **Length** | **Label**                       | **Possible Value / Description**                                                                                |
| ----------------------------------- | ------------- | ---------- | ------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| **CENTERCODE**                      | VARCHAR       | 20         | Center Code                     | Link from Center Module                                                                                         |
| **ENTERPRISECODE**                  | VARCHAR       | 20         | Enterprise Code                 | Link from Enterprise Module                                                                                     |
| **PRODUCTIONCODE**                  | VARCHAR       | 20         | Production Code                 | `CEN-ENT-MPROD001`                                                                                              |
| **ProductionType**                  | VARCHAR       | 250        | Production Type                 | `MILK PRODUCTION`                                                                                               |
| **TRANSDATE**                       | DATE          | 8          | Date Reported (Autofill)        |                                                                                                                 |
| **Year**                            | NUMERIC       |            | Year                            |                                                                                                                 |
| **Month**                           | NUMERIC       |            | Month                           |                                                                                                                 |
| **Week**                            | NUMERIC       |            | Week                            |                                                                                                                 |
| **Duration**                        |               |            | Automatic from year/month/week  | e.g., `Jan 10–16`                                                                                               |
| **Milk Volume (kgs)**               | NUMERIC       |            | Milk Volume (kgs)               | Input                                                                                                           |
| **MilkVolume From Farmers**         | NUMERIC       |            | Milk Volume From Farmers        | Automatic from Farmers (based on Year/Month/Week)                                                               |
| **RawMilkSupplier**                 | NUMERIC       |            | Raw Milk Supplier               | Input                                                                                                           |
| **BuyingPrice**                     | NUMERIC       |            | Buying Price                    | Input                                                                                                           |
| **Farmgateprice**                   | NUMERIC       |            | Farm Gate Price                 | Default Last (from last inputted farm gate price)                                                               |
| **Fed to Calves (kgs)**             | NUMERIC       |            | Fed to Calves (kgs)             | Input                                                                                                           |
| **MilkSpoiled**                     | NUMERIC       |            | Milk Spoiled                    | Input                                                                                                           |
| **FamilyMilkConsumption**           | NUMERIC       |            | Family Milk Consumption         | Input                                                                                                           |
| **milkingTimes**                    | NUMERIC       |            | # of Times Milking              | `AM`, `PM`, or `AM/PM`                                                                                          |
| **NumberofLactating**               | NUMERIC       |            | Number of Lactating             | Input                                                                                                           |
| **Average Milk per Animal per Day** | Formula Field |            | Average Milk per Animal per Day | Formula: `(# of Lactating / 20 days / Milk Volume / Times Milking)` <br>Note: `IF AM or PM = 1`, `IF AM/PM = 2` |
| **Number of Staff Male**            | NUMERIC       |            | Number of Staff (Male)          |                                                                                                                 |
| **Number of Staff Female**          | NUMERIC       |            | Number of Staff (Female)        |                                                                                                                 |
| **Average Monthly Salary of Staff** | NUMERIC       |            | Average Monthly Salary of Staff |                                                                                                                 |
| **MilkSales**                       |               |            | Milk Sales                      |                                                                                                                 |
| **MilkTraded**                      |               |            | Input                           |                                                                                                                 |
| **RawMilkTraded**                   |               |            | Raw Milk Traded                 | `MilkTraded * Farmgateprice`                                                                                    |
| **ValueAddedProd**                  |               |            | Value Added Product             | Automatic from b.4.1 TOTAL SALES if PMO TYPE = OTHERS                                                           |
| **DairyBox**                        |               |            | Dairy Box                       | Automatic from b.4.1 TOTAL SALES if PMO TYPE = DAIRY BOX                                                        |
| **MilkFeeding**                     |               |            | Milk Feeding                    | Input                                                                                                           |


Currenlty the proposed table `TBL_MILK_PRODUCTION` is denormalized and doest map the flow of milk production effectively. We need to create a more normalized data model to capture the various aspects of milk production, including sources, usage, sales, wastage, operational costs. 

The milk production should be streamlined, hence we need to break down the table into multiple related tables.

1. `Milk Production Table` To capture production details like indentifiers, sources, volume, date, type, etc, status; Important note here that the production will be in a pipeline, i.e., Draft, Submitted, Approved, Rejected, Completed. 

2. `Milk Sales Table` To capture sales details including quantities sold, prices, and buyers.

3. `Milk Usage Table` To capture internal usage of milk such as feeding calves, family consumption, milk feeding programs, etc.

4. `Milk Wastage Table` To capture details of spoiled milk and reasons.

5. `Milk Cost Table` To capture operational costs related to milk production including staff salaries, buying prices, etc.


Phase 1: Everyging will be uploaded thourgh imports, hence we will create an excell template for the same. 

`Objective`

- is to inform the stackholders about the new data model and how we it will implement (`Multisheet Excel Import`) to capture milk production data effectively.
- create a technical data modeling in the backend to support the new milk production flow.
