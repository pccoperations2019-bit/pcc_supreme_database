# Project Overview

S.U.P.R.E.M.E stands for `Sustainability, Update report, Profile, Reports, Engage, and Monitoring and Execution`, ( Coined by the stakeholder: Philippine Carabao Center - PCC). 

Profiling & Providing Insights of the Performance Instutions Herds (Institutions with Carabao Herds)
and Major Program: CBED (Carabao-based Enterprise Development)

## Project Purpose

Provide Data Warehousing and Business Intelligence Solution for the PCC to help them monitor and evaluate their programs and projects more effectively.

PCC have herb of carabao, for the production, they are interested to monitor the performance of their prodcution: 

- Milk Production - metric of unit is per liter
- Meat Production - 
- Manure Production - metric of unit is in kgs 

## Background

The Philippine Carabao Center (PCC) is a government agency under the Department of Agriculture (DA) mandated to promote the carabao as a source of food, draft animal, and hide for the farmers and rural communities in the Philippines.

## Architecture Descitions

Monolithic Architecture - Chosen for its simplicity and ease of deployment, especially for small to medium-sized applications. It allows for faster development cycles and easier management of the codebase.
Multi-Tenancy - Implemented to allow multiple agencies and centers to operate within the same application instance while keeping their data isolated. This is crucial for organizations like PCC that have various branches and departments.
VILT Stack - Selected for its modern approach to web development, combining VueJS for the frontend, InertiaJS for seamless server-side rendering, Laravel for robust backend functionality, and TailwindCSS for efficient styling.

## Data Dictionary

1. Agency
2. Center
3. Partners
4. Farmer
5. Herds
6. Services -> Programs
7. Services -> Projects

## Technical Stacks

- Monolithic Architecture
- VILT - VueJS, InertialJs, Laravel, TailwindCSS
- Mysql 
- Data Warehousing
- Native Implementation (Customize) of Business Intelligence: Preset and Coded. 

## Coding Standards

- Laravel Standards and Best Practices for 2025 / php 8.4

- VueJS V3 Style Guide, javascript ES2024 Standards

## References

https://www.pcc.gov.ph/enterprise-development/