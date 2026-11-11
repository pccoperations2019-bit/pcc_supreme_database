## POC Map of the Philippines - V1 (Public Data)

### Objective

The POC Map of the Philippines aims to provide a visual representation of the regional centers of the DA-PCC (Department of Agriculture – Philippine Carabao Center).

---

### Acceptance Criteria

1. Users can log in to the system using their credentials (username and password).
   *Note: As of now, there are no roles or authorization levels — only a simple login system.*

2. Users can view a map of the Philippines with markers indicating the locations of DA-PCC regional centers.
   *(Displayed on the left side of the screen.)*

---

### Technical Specifications

**Frontend Layout:**

* Left half: Login interface
* Right half: Map display

---

### Backend / Data Model

**Entities:**

* **User**

  * Uses Laravel’s default columns.

* **Center**

  * `name`
  * `address_id` (foreign key referencing the *addresses* table)
  * `description`
  * *Additional columns may be added later.*

* **Address** *(Polymorphic relationship — “morph to many” with centers)*

  * `addressable_id`
  * `addressable_type`
  * `street`
  * `barangay`
  * `city_or_municipality`
  * `province`
  * `region`
  * `zip_code`
  * `country`
  * `latitude`
  * `longitude`
  * `marker_icon` *(required; a default icon will be used if none is provided)*


| **Level**                  | **Example**                                       | **Suggested Field Name** | **Notes**                                                                |
| -------------------------- | ------------------------------------------------- | ------------------------ | ------------------------------------------------------------------------ |
| **Street / Sitio / Purok** | “Purok 2, Mabini St.”                             | `street`                 | Optional for rural data, useful for precise mapping.                     |
| **Barangay**               | “Brgy. San Jose”                                  | `barangay`               | Smallest administrative unit; *important for accurate location mapping*. |
| **City / Municipality**    | “City of San Fernando” / “Municipality of Guimba” | `city_municipality`      | Combine into one field to handle both cases cleanly.                     |
| **Province**               | “Nueva Ecija”                                     | `province`               | Provincial-level boundary.                                               |
| **Region**                 | “Region III – Central Luzon”                      | `region`                 | Optional, but useful for grouping centers by regional office.            |
| **ZIP Code**               | “3115”                                            | `zip_code`               | Based on Philippine Postal Corporation data.                             |
| **Country**                | “Philippines”                                     | `country`                | Usually constant.                                                        |
| **Latitude / Longitude**   | `15.486° N, 120.857° E`                           | `latitude`, `longitude`  | Required for map markers.                                                |

---

### Enumerations

* **Marker**