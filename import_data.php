<?php
require_once 'config/database.php';

class DataImporter {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    private function parseDate($dateString) {
        if (empty($dateString) || trim($dateString) === '') {
            return null;
        }
        
        $dateString = trim($dateString);
        
        $formats = [
            'd-m-Y H:i',
            'm/d/Y H:i', 
            'd-m-Y H:i:s',
            'm/d/Y H:i:s',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date;
            }
        }
        
        if (strpos($dateString, '-') !== false && strpos($dateString, '/') === false) {
            $date = DateTime::createFromFormat('d-m-Y H:i', $dateString);
            if ($date !== false) return $date;
        } elseif (strpos($dateString, '/') !== false) {
            $date = DateTime::createFromFormat('m/d/Y H:i', $dateString);
            if ($date !== false) return $date;
        }
        
        error_log("Failed to parse date: " . $dateString);
        return null;
    }

    public function importAllData() {
        $this->importEntities();
        $this->importCardSwipes();
        $this->importWifiLogs();
        $this->importLibraryCheckouts();
        $this->importLabBookings();
        $this->importFreeTextNotes();
        $this->importCctvFrames();
        
        echo "All data imported successfully!";
    }

    private function importEntities() {
        $file = 'data/student_or_staff_profiles.csv';
        if (!file_exists($file)) {
            echo "Entities file not found: $file<br>";
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo "Cannot open file: $file<br>";
            return;
        }

        $header = fgetcsv($handle);
        echo "Importing entities...<br>";
        
        $count = 0;
        $batch = [];
        $batchSize = 1000;
        
        while ($row = fgetcsv($handle)) {
            if (count($row) < 10) {
                continue;
            }
            
            $batch[] = [
                $row[0], $row[1], $row[2], $row[3], $row[4],
                $row[5] ?: null, $row[6] ?: null, $row[7] ?: null, 
                $row[8] ?: null, $row[9] ?: null
            ];
            
            if (count($batch) >= $batchSize) {
                $this->insertEntitiesBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        
        if (count($batch) > 0) {
            $this->insertEntitiesBatch($batch);
            $count += count($batch);
        }
        
        fclose($handle);
        echo "Imported $count entities<br>";
    }

    private function insertEntitiesBatch($batch) {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $values = array_merge($values, $row);
        }
        
        $sql = "INSERT INTO entities 
                (entity_id, name, role, email, department, student_id, staff_id, card_id, device_hash, face_id) 
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
    }

    private function importCardSwipes() {
        $file = 'data/campus_card_swipes.csv';
        if (!file_exists($file)) {
            echo "Card swipes file not found: $file<br>";
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo "Cannot open file: $file<br>";
            return;
        }

        fgetcsv($handle);
        echo "Importing card swipes...<br>";
        
        $count = 0;
        $batch = [];
        $batchSize = 1000;
        
        while ($row = fgetcsv($handle)) {
            if (count($row) < 3) {
                continue;
            }
            
            $date = $this->parseDate($row[2]);
            if (!$date) {
                continue;
            }
            
            $batch[] = [
                $row[0], $row[1], $date->format('Y-m-d H:i:s')
            ];
            
            if (count($batch) >= $batchSize) {
                $this->insertCardSwipesBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        
        if (count($batch) > 0) {
            $this->insertCardSwipesBatch($batch);
            $count += count($batch);
        }
        
        fclose($handle);
        echo "Imported $count card swipes<br>";
    }

    private function insertCardSwipesBatch($batch) {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?)';
            $values = array_merge($values, $row);
        }
        
        $sql = "INSERT INTO card_swipes (card_id, location_id, timestamp) 
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
    }

    private function importWifiLogs() {
        $file = 'data/wifi_associations_logs.csv';
        if (!file_exists($file)) {
            echo "WiFi logs file not found: $file<br>";
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo "Cannot open file: $file<br>";
            return;
        }

        fgetcsv($handle);
        echo "Importing WiFi logs...<br>";
        
        $count = 0;
        $batch = [];
        $batchSize = 1000;
        
        while ($row = fgetcsv($handle)) {
            if (count($row) < 3) {
                continue;
            }
            
            $date = $this->parseDate($row[2]);
            if (!$date) {
                continue;
            }
            
            $batch[] = [
                $row[0], $row[1], $date->format('Y-m-d H:i:s')
            ];
            
            if (count($batch) >= $batchSize) {
                $this->insertWifiLogsBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        
        if (count($batch) > 0) {
            $this->insertWifiLogsBatch($batch);
            $count += count($batch);
        }
        
        fclose($handle);
        echo "Imported $count WiFi logs<br>";
    }

    private function insertWifiLogsBatch($batch) {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?)';
            $values = array_merge($values, $row);
        }
        
        $sql = "INSERT INTO wifi_logs (device_hash, ap_id, timestamp) 
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
    }

    private function importLibraryCheckouts() {
        $file = 'data/library_checkouts.csv';
        if (!file_exists($file)) {
            echo "Library checkouts file not found: $file<br>";
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo "Cannot open file: $file<br>";
            return;
        }

        fgetcsv($handle);
        echo "Importing library checkouts...<br>";
        
        $count = 0;
        $batch = [];
        $batchSize = 1000;
        
        while ($row = fgetcsv($handle)) {
            if (count($row) < 4 || empty($row[0]) || trim($row[0]) === '') {
                continue;
            }
            
            $date = $this->parseDate($row[3]);
            if (!$date) {
                continue;
            }
            
            $batch[] = [
                $row[0], $row[1], $row[2], $date->format('Y-m-d H:i:s')
            ];
            
            if (count($batch) >= $batchSize) {
                $this->insertLibraryCheckoutsBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        
        if (count($batch) > 0) {
            $this->insertLibraryCheckoutsBatch($batch);
            $count += count($batch);
        }
        
        fclose($handle);
        echo "Imported $count library checkouts<br>";
    }

    private function insertLibraryCheckoutsBatch($batch) {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?, ?)';
            $values = array_merge($values, $row);
        }
        
        $sql = "INSERT INTO library_checkouts (checkout_id, entity_id, book_id, timestamp) 
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
    }

    private function importLabBookings() {
        $file = 'data/lab_bookings.csv';
        if (!file_exists($file)) {
            echo "Lab bookings file not found: $file<br>";
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo "Cannot open file: $file<br>";
            return;
        }

        fgetcsv($handle);
        echo "Importing lab bookings...<br>";
        
        $count = 0;
        $batch = [];
        $batchSize = 1000;
        
        while ($row = fgetcsv($handle)) {
            if (count($row) < 6) {
                continue;
            }
            
            $startDate = $this->parseDate($row[3]);
            $endDate = $this->parseDate($row[4]);
            if (!$startDate || !$endDate) {
                continue;
            }
            
            $batch[] = [
                $row[0], $row[1], $row[2], 
                $startDate->format('Y-m-d H:i:s'), 
                $endDate->format('Y-m-d H:i:s'),
                $row[5]
            ];
            
            if (count($batch) >= $batchSize) {
                $this->insertLabBookingsBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        
        if (count($batch) > 0) {
            $this->insertLabBookingsBatch($batch);
            $count += count($batch);
        }
        
        fclose($handle);
        echo "Imported $count lab bookings<br>";
    }

    private function insertLabBookingsBatch($batch) {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?)';
            $values = array_merge($values, $row);
        }
        
        $sql = "INSERT INTO lab_bookings (booking_id, entity_id, room_id, start_time, end_time, attended) 
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
    }

    private function importFreeTextNotes() {
        $file = 'data/free_text_notes.csv';
        if (!file_exists($file)) {
            echo "Free text notes file not found: $file<br>";
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo "Cannot open file: $file<br>";
            return;
        }

        fgetcsv($handle);
        echo "Importing free text notes...<br>";
        
        $count = 0;
        $batch = [];
        $batchSize = 1000;
        
        while ($row = fgetcsv($handle)) {
            if (count($row) < 5) {
                continue;
            }
            
            $date = $this->parseDate($row[4]);
            if (!$date) {
                continue;
            }
            
            $batch[] = [
                $row[0], $row[1], $row[2], $row[3], $date->format('Y-m-d H:i:s')
            ];
            
            if (count($batch) >= $batchSize) {
                $this->insertFreeTextNotesBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        
        if (count($batch) > 0) {
            $this->insertFreeTextNotesBatch($batch);
            $count += count($batch);
        }
        
        fclose($handle);
        echo "Imported $count free text notes<br>";
    }

    private function insertFreeTextNotesBatch($batch) {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?)';
            $values = array_merge($values, $row);
        }
        
        $sql = "INSERT INTO free_text_notes (note_id, entity_id, category, text, timestamp) 
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
    }

    private function importCctvFrames() {
        $file = 'data/cctv_frames.csv';
        if (!file_exists($file)) {
            echo "CCTV frames file not found: $file<br>";
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            echo "Cannot open file: $file<br>";
            return;
        }

        fgetcsv($handle);
        echo "Importing CCTV frames...<br>";
        
        $count = 0;
        $batch = [];
        $batchSize = 1000;
        
        while ($row = fgetcsv($handle)) {
            if (count($row) < 4) {
                continue;
            }
            
            $date = $this->parseDate($row[2]);
            if (!$date) {
                continue;
            }
            
            $batch[] = [
                $row[0], $row[1], $date->format('Y-m-d H:i:s'), $row[3] ?: null
            ];
            
            if (count($batch) >= $batchSize) {
                $this->insertCctvFramesBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        
        if (count($batch) > 0) {
            $this->insertCctvFramesBatch($batch);
            $count += count($batch);
        }
        
        fclose($handle);
        echo "Imported $count CCTV frames<br>";
    }

    private function insertCctvFramesBatch($batch) {
        $placeholders = [];
        $values = [];
        
        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?, ?)';
            $values = array_merge($values, $row);
        }
        
        $sql = "INSERT INTO cctv_frames (frame_id, location_id, timestamp, face_id) 
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
    }
}

$importer = new DataImporter();
$importer->importAllData();
?>