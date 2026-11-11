Consolidation Report Template

# Consolidation Report 

There are 3 templates of consolidation reports base in the Buffallo-Derived Product. 

1. Milk Consolidation Report
2. Meat Consolidation Report
3. Manure Consolidation Report

## 1. Milk Consolidation Report

A. Create an google sheets with multiple tables named per Center. see: example. https://docs.google.com/spreadsheets/d/1gwHbqVI1uxmqW1fZ6sasZniSVm2Is-dOY8ZUXS-FpZ8/edit?gid=0#gid=0

B. Each table should have the following columns:

Period and Filtering
- Period Start
- Period End

Source Information ( nullable in the fieds)

- Partner
- Farmer
- Supplier (this is vendor that supply raw milk directly to the center)

Volume Summary
- Total Raw Milk 
- Total From Center 
- Total From Partners 
- Total From Farmers 
- Total From Suppliers

Financial Summary

- Average Buying Price
- Total Cost 
- Average Selling Price
- Revenue Generated
- Processing Cost

Note: please create demo data for testing purpose.

This will be uploaded via DPM (Document Processing Module) and will be parsed and stored in the DB. 

C. (Backend) Create now Resource (Model, Migration) for Milk Production Consolidation Report. 

D. (Views) Showcase the data in the IH Context; and in page Consolidation > Milk 


