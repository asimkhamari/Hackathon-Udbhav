# CampusSight: Campus Entity Resolution & Security Monitoring System

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Python](https://img.shields.io/badge/Python-3.8+-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://python.org)
[![Scikit-Learn](https://img.shields.io/badge/Scikit--Learn-F7931E?style=for-the-badge&logo=scikit-learn&logoColor=white)](https://scikit-learn.org)

---

## Overview

**CampusSight** is an intelligent platform designed to unify fragmented campus data into a single, actionable view.  
It helps security and operations teams move from reactive monitoring to proactive intelligence by connecting information from access logs, Wi-Fi connections, library checkouts, CCTV footage, and more.

The system enables entity resolution, timeline reconstruction, predictive analytics, and intelligent alerting; all accessible through a web-based dashboard.

---

## Key Features

### Unified Entity Resolution
- **Cross-Source Integration:** Combines data from card swipes, Wi-Fi logs, library systems, lab bookings, and CCTV.  
- **Digital Twin Creation:** Links multiple identifiers (ID cards, devices, face recognition) into a single entity profile.  
- **Real-Time Search:** Enables quick searches across all connected data sources.

### Intelligent Dashboard
- **Activity Timeline:** Visualize chronological movement and activity for each entity.  
- **Security Alerts:** Automatically detects inactivity (more than 12 hours) or unusual behavior patterns.  

### Predictive Analytics
- **Location Prediction:** Predicts locations using a time-aware machine learning model.  
<!-- - **Explainable AI:** Provides confidence scores and probability breakdowns for predictions.   -->
<!-- - **Temporal Modeling:** Accounts for daily and weekly behavioral patterns. -->

<!-- ### Privacy & Security
- **Role-Based Access:** Controls visibility and permissions by user role.  
- **Data Protection:** Safeguards personally identifiable information (PII).  
- **Audit Logging:** Maintains a complete record of all system actions. -->

---

## System Architecture

### 1. Data Layer
- **MySQL Database:** Structured and indexed schema with entity mappings.  
- **Structured Data:** Card swipes, Wi-Fi logs, library records, and lab bookings.  
- **Unstructured Data:** Notes, incident reports, and text-based records.  
- **Visual Data:** CCTV metadata integrated with face recognition.

### 2. Machine Learning Layer
- **Model:** Random Forest Classifier trained on time-based features.  
- **Feature Engineering:** Includes entity roles, departments, and temporal attributes.  
- **Persistence:** Trained models saved and reused for prediction requests.

### 3. Application Layer
- **Backend:** PHP RESTful API for data retrieval and management.  
- **ML Engine:** Python integration using Scikit-learn, Pandas, and NumPy.  
- **Frontend:** Tailwind CSS-based responsive interface with interactive dashboards.

---

## Project Structure

CAMPUS_ENTITY_SYSTEM/
├── cache/ # Cache directory for temporary files
├── config/ # Configuration files
│ └── database.php # Database configuration
│
├── data/ # Data directory
│ ├── face_images/ # Face recognition images
│ ├── student_or_staff_profiles.csv
│ ├── campus_card_swipes.csv
│ ├── wifi_associations_logs.csv
│ ├── library_checkouts.csv
│ ├── lab_bookings.csv
│ ├── free_text_notes.csv
│ └── cctv_frames.csv
│
├── models/ # Data models
│ └── Entity.php # Entity model class
│
├── prediction/ # Machine Learning and prediction files
│ ├── data_with_input.py # Python ML script
│ ├── model_feature_columns.json # Model feature definitions
│ ├── trained_location_model.joblib # Trained model file
│ └── predict_location.php # Location prediction API
│
├── alerts.php # Security alerts page
├── browse.php # Entity browsing interface
├── entity.php # Individual entity profile page
├── import_data.php # Data import utility
├── index.php # Main dashboard entry point
├── optimize_database.php # Database optimization script
├── search.php # Entity search functionality
├── setup_database.php # Database initialization script
├── test_python.php # Python environment test
└── README.md # Project documentation

---

## Quick Start

### Prerequisites
- PHP 8.0 or higher  
- MySQL 8.0 or higher  
- Python 3.8+ with `scikit-learn`, `pandas`, and `numpy`  
- XAMPP or WAMP (recommended for local development)

### Installation

#### 1. Clone the Repository

````markdown

git clone https://github.com/asimkhamari/Hackathon-Udbhav.git
cd campus-entity-system
````

---

#### 2. Set Up the Database

Create a new database with name `campus-entity-system` in phpmyadmin.
Import this file [https://drive.google.com/file/d/12b--aK9wCoIhCh-UQj1OmGj8n5mAk91L/view?usp=sharing] inside the database created.


---

#### 3. Start the Local Server

* If you are using **XAMPP**, move the project folder into your `htdocs/` directory.
* Then open your browser and go to:

  ```
  http://localhost/campus-entity-system/index.php
  ```

---

### Configuration

Update the database credentials in `config/database.php` as needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_entity_system');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
```

---

### Usage Examples

#### 1. Entity Search

* Search entities by **name**, **email**, **ID**, or **facial ID**.
* Filter results by **role** (student/staff) or **department**.
* View a full timeline of activities and related records for any entity.

---

#### 2. Location Prediction

**Example Request**

```json
{
  "start_time": "2025-09-16 14:00",
  "end_time": "2025-10-16 17:00",
  "entity_id": "E100011"
}
```

**Example Response**

```json
{
  "status": "success",
  "most_likely_location": "Library",
  "confidence": 0.85,
  "breakdown": {
    "Library": 0.85,
    "Lab": 0.12,
    "Cafeteria": 0.03
  }
}
```

---

### Security Monitoring

* Automatic alerts for entities inactive for **more than 12 hours**.
* Live dashboard displaying **campus-wide activity statistics**.

---

### Database Schema

| Table               | Description                              |
| ------------------- | ---------------------------------------- |
| `entities`          | Master record of all entities            |
| `card_swipes`       | Access control system logs               |
| `wifi_logs`         | Wi-Fi connection data                    |
| `library_checkouts` | Library transaction records              |
| `lab_bookings`      | Lab reservations and attendance          |
| `free_text_notes`   | Unstructured notes and reports           |
| `cctv_frames`       | CCTV frame metadata and recognition data |

---

### Contributing

1. **Fork** this repository.
2. **Create a feature branch:**

   ```bash
   git checkout -b feature/new-feature
   ```
3. **Commit your changes:**

   ```bash
   git commit -m "Add new feature"
   ```
4. **Push to your fork and open a Pull Request.**

---

## License

This project is licensed under the **MIT License**.

---

## Support

If you encounter issues or have questions:

- Open an issue on [GitHub](https://github.com/asimkhamari/Hackathon-Udbhav/issues)

---

## Acknowledgments

**CampusSight** is built with contributions and technologies from the following open-source tools:

- **Machine Learning:** Scikit-learn, Pandas, NumPy  
- **Frontend:** Tailwind CSS, Font Awesome, Heroicons  
- **Backend:** PHP, MySQL  

---

**CampusSight** — Transforming campus operations through unified data and intelligent analytics.
