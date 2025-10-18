<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

function run_prediction($start_time, $end_time, $entity_id) {
    $python_script = __DIR__ . '/data_with_input.py';
    $command = 'python "' . $python_script . '" predict "' . $start_time . '" "' . $end_time . '" "' . $entity_id . '"';
    $output = shell_exec($command . ' 2>&1');
    return $output;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    $start_time = $input['start_time'] ?? '';
    $end_time = $input['end_time'] ?? '';
    $entity_id = $input['entity_id'] ?? '';

    if (empty($start_time) || empty($end_time) || empty($entity_id)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'All fields are required: start_time, end_time, and entity_id'
        ]);
        exit;
    }
    
    $python_output = run_prediction($start_time, $end_time, $entity_id);

    $json_pattern = '/\{"most_likely_location".*?"status".*?\}$/';
    preg_match($json_pattern, $python_output, $matches);
    
    if (!empty($matches)) {
        $json_output = $matches[0];
        $result = json_decode($json_output, true);
        
        if ($result && isset($result['status'])) {
            echo json_encode($result);
            exit;
        }
    }

    $json_start = strpos($python_output, '{"most_likely_location":');
    if ($json_start === false) {
        $json_start = strpos($python_output, '{"status":');
    }
    
    if ($json_start !== false) {
        $json_output = substr($python_output, $json_start);
        $result = json_decode($json_output, true);
        
        if ($result && isset($result['status'])) {
            echo json_encode($result);
            exit;
        }
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Prediction failed - no valid output from Python script',
        'raw_output' => $python_output
    ]);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Prediction System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 10px;
            display: none;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .breakdown {
            margin-top: 15px;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .confidence-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 10px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .confidence-fill {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            height: 100%;
            transition: width 0.5s;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📍 Location Prediction System</h1>
            <p>Predict where people are most likely to be based on time and profile</p>
        </div>
        
        <div class="form-container">
            <form id="predictionForm">
                <div class="form-group">
                    <label for="start_time">Start Time:</label>
                    <input type="datetime-local" id="start_time" name="start_time" required>
                </div>
                
                <div class="form-group">
                    <label for="end_time">End Time:</label>
                    <input type="datetime-local" id="end_time" name="end_time" required>
                </div>
                
                <div class="form-group">
                    <label for="entity_id">Entity ID:</label>
                    <input type="text" id="entity_id" name="entity_id" required placeholder="Enter entity ID">
                </div>
                
                <button type="submit" class="btn">Predict Location</button>
            </form>

            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Running prediction... This may take a few seconds.</p>
            </div>
            
            <div id="result" class="result"></div>
        </div>
    </div>

    <script>
        document.getElementById('predictionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button');
            const originalText = submitBtn.textContent;
            const loadingDiv = document.getElementById('loading');
            const resultDiv = document.getElementById('result');
            
            loadingDiv.style.display = 'block';
            resultDiv.style.display = 'none';
            submitBtn.textContent = 'Predicting...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            const data = {
                start_time: formData.get('start_time').replace('T', ' ') + ':00',
                end_time: formData.get('end_time').replace('T', ' ') + ':00',
                entity_id: formData.get('entity_id')
            };
            
            try {
                const response = await fetch('predict_location.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                loadingDiv.style.display = 'none';
                
                if (result.status === 'success') {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h3>🎯 Prediction Results</h3>
                            <p><strong>Most Likely Location:</strong> ${result.most_likely_location}</p>
                            <p><strong>Confidence:</strong> ${(result.confidence * 100).toFixed(1)}%</p>
                            
                            <div class="breakdown">
                                <h4>Detailed Breakdown:</h4>
                                ${Object.entries(result.breakdown).map(([location, percentage]) => `
                                    <div class="breakdown-item">
                                        <span>Location ${location}</span>
                                        <span>${(percentage * 100).toFixed(1)}%</span>
                                    </div>
                                    <div class="confidence-bar">
                                        <div class="confidence-fill" style="width: ${percentage * 100}%"></div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h3>❌ Error</h3>
                            <p>${result.message || 'Prediction failed'}</p>
                            ${result.debug ? `<p><small>Debug info: ${result.debug}</small></p>` : ''}
                        </div>
                    `;
                }
                
                resultDiv.style.display = 'block';
                
            } catch (error) {
                loadingDiv.style.display = 'none';
                resultDiv.innerHTML = `
                    <div class="error">
                        <h3>❌ Request Failed</h3>
                        <p>${error.message}</p>
                    </div>
                `;
                resultDiv.style.display = 'block';
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });

        const now = new Date();
        const startTime = new Date(now.getTime() + 60 * 60 * 1000);
        const endTime = new Date(startTime.getTime() + 3 * 60 * 60 * 1000);
        
        document.getElementById('start_time').value = formatDateTime(startTime);
        document.getElementById('end_time').value = formatDateTime(endTime);
        
        function formatDateTime(date) {
            return date.toISOString().slice(0, 16);
        }
    </script>
</body>
</html>