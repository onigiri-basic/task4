<?php
// Настройки подключения к БД
$host = 'localhost';
$dbname = 'u82671'; // замените на ваш логин
$username = 'u82671'; // замените на ваш логин
$password = '1266050'; // замените на ваш пароль

$errors = [];
$success = false;
$formData = [];

// Функция для безопасного получения POST данных
function getPostValue($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Валидация ФИО
function validateFullname($fullname) {
    if (empty($fullname)) {
        return 'ФИО обязательно для заполнения';
    }
    if (strlen($fullname) > 150) {
        return 'ФИО не должно превышать 150 символов';
    }
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $fullname)) {
        return 'ФИО может содержать только буквы, пробелы и дефисы';
    }
    return null;
}

// Валидация телефона
function validatePhone($phone) {
    if (!empty($phone)) {
        if (strlen($phone) > 50) {
            return 'Телефон не должен превышать 50 символов';
        }
        // Простая проверка формата телефона
        if (!preg_match('/^[\+\d\s\-\(\)]+$/', $phone)) {
            return 'Некорректный формат телефона';
        }
    }
    return null;
}

// Валидация email
function validateEmail($email) {
    if (empty($email)) {
        return 'E-mail обязателен для заполнения';
    }
    if (strlen($email) > 100) {
        return 'E-mail не должен превышать 100 символов';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Некорректный формат e-mail';
    }
    return null;
}

// Валидация даты рождения
function validateBirthdate($birthdate) {
    if (!empty($birthdate)) {
        $date = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$date || $date->format('Y-m-d') !== $birthdate) {
            return 'Некорректная дата рождения';
        }
        // Проверка, что дата не в будущем
        if ($date > new DateTime()) {
            return 'Дата рождения не может быть в будущем';
        }
    }
    return null;
}

// Валидация пола
function validateGender($gender) {
    $allowed = ['male', 'female', 'other', 'unspecified'];
    if (!in_array($gender, $allowed)) {
        return 'Некорректное значение пола';
    }
    return null;
}

// Валидация языков программирования
function validateLanguages($languages, $pdo) {
    if (empty($languages)) {
        return 'Выберите хотя бы один язык программирования';
    }
    if (count($languages) > 12) {
        return 'Выбрано слишком много языков';
    }
    
    // Проверяем, что все выбранные языки существуют в БД
    $placeholders = str_repeat('?,', count($languages) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name IN ($placeholders)");
    $stmt->execute($languages);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($existing) != count($languages)) {
        return 'Один или несколько выбранных языков не поддерживаются';
    }
    return null;
}

// Валидация биографии
function validateBiography($bio) {
    if (!empty($bio)) {
        if (strlen($bio) > 10000) {
            return 'Биография не должна превышать 10000 символов';
        }
    }
    return null;
}

// Валидация чекбокса контракта
function validateContract($contract) {
    if ($contract != 'on' && $contract != '1' && $contract !== true) {
        return 'Необходимо подтвердить ознакомление с контрактом';
    }
    return null;
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Сбор данных
        $formData = [
            'fullname' => getPostValue('fullname'),
            'phone' => getPostValue('phone'),
            'email' => getPostValue('email'),
            'birthdate' => getPostValue('birthdate'),
            'gender' => getPostValue('gender', 'unspecified'),
            'languages' => isset($_POST['fav_langs']) ? $_POST['fav_langs'] : [],
            'biography' => getPostValue('bio'),
            'contract' => isset($_POST['contract_agreed']) ? $_POST['contract_agreed'] : ''
        ];
        
        // Валидация всех полей
        $errors['fullname'] = validateFullname($formData['fullname']);
        $errors['phone'] = validatePhone($formData['phone']);
        $errors['email'] = validateEmail($formData['email']);
        $errors['birthdate'] = validateBirthdate($formData['birthdate']);
        $errors['gender'] = validateGender($formData['gender']);
        $errors['languages'] = validateLanguages($formData['languages'], $pdo);
        $errors['biography'] = validateBiography($formData['biography']);
        $errors['contract'] = validateContract($formData['contract']);
        
        // Фильтруем ошибки (убираем null значения)
        $errors = array_filter($errors);
        
        // Если ошибок нет - сохраняем в БД
        if (empty($errors)) {
            $pdo->beginTransaction();
            
            try {
                // Вставка в таблицу applications
                $stmt = $pdo->prepare("
                    INSERT INTO applications (fullname, phone, email, birthdate, gender, biography, contract_agreed)
                    VALUES (:fullname, :phone, :email, :birthdate, :gender, :biography, :contract)
                ");
                
                $stmt->execute([
                    ':fullname' => $formData['fullname'],
                    ':phone' => $formData['phone'] ?: null,
                    ':email' => $formData['email'],
                    ':birthdate' => $formData['birthdate'] ?: null,
                    ':gender' => $formData['gender'],
                    ':biography' => $formData['biography'] ?: null,
                    ':contract' => $formData['contract'] == 'on' ? 1 : 0
                ]);
                
                $applicationId = $pdo->lastInsertId();
                
                // Вставка языков программирования
                $stmtLang = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
                $stmtInsert = $pdo->prepare("
                    INSERT INTO application_languages (application_id, language_id)
                    VALUES (?, ?)
                ");
                
                foreach ($formData['languages'] as $langName) {
                    $stmtLang->execute([$langName]);
                    $langId = $stmtLang->fetchColumn();
                    if ($langId) {
                        $stmtInsert->execute([$applicationId, $langId]);
                    }
                }
                
                $pdo->commit();
                $success = true;
                
                // Очищаем данные формы после успешного сохранения
                $formData = [];
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors['db'] = 'Ошибка сохранения данных: ' . $e->getMessage();
            }
        }
        
    } catch (PDOException $e) {
        $errors['db'] = 'Ошибка подключения к базе данных: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета разработчика | Сохранение в БД</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(145deg, #e0eaf4 0%, #cfdef3 100%);
            font-family: 'Segoe UI', 'Roboto', system-ui, sans-serif;
            padding: 2rem 1.5rem;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .form-container {
            max-width: 980px;
            width: 100%;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 2rem;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #1a2a3f, #0f1a2a);
            padding: 1.8rem 2.5rem;
            color: white;
        }
        
        .form-header h1 {
            font-weight: 600;
            font-size: 1.9rem;
            margin-bottom: 0.3rem;
        }
        
        .form-header p {
            font-size: 0.95rem;
            opacity: 0.85;
        }
        
        .form-body {
            padding: 2rem 2.5rem;
        }
        
        .field-group {
            margin-bottom: 1.7rem;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .field-group label {
            width: 180px;
            font-weight: 600;
            color: #1f2e45;
            font-size: 0.95rem;
            padding-top: 0.6rem;
            flex-shrink: 0;
        }
        
        .field-group .input-wrapper {
            flex: 1;
            min-width: 220px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s ease;
            outline: none;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
        }
        
        .error {
            border-color: #f87171 !important;
            background-color: #fff5f5 !important;
        }
        
        .error-message {
            color: #dc2626;
            font-size: 0.75rem;
            margin-top: 0.3rem;
            margin-left: 0.2rem;
        }
        
        .radio-group {
            display: flex;
            gap: 1.8rem;
            align-items: center;
            flex-wrap: wrap;
            background: #f8fafc;
            padding: 0.6rem 1rem;
            border-radius: 1rem;
            border: 1.5px solid #e2edf7;
        }
        
        .radio-group label {
            width: auto;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            cursor: pointer;
            padding-top: 0;
        }
        
        .radio-group input {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f8fafd;
            padding: 0.6rem 1rem;
            border-radius: 1rem;
            border: 1px solid #e2edf7;
        }
        
        .checkbox-wrapper input {
            width: 20px;
            height: 20px;
            accent-color: #2563eb;
        }
        
        .checkbox-wrapper label {
            font-weight: 500;
            cursor: pointer;
        }
        
        select[multiple] {
            min-height: 140px;
            background: white;
        }
        
        select[multiple] option {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #ecf3f9;
        }
        
        select[multiple] option:checked {
            background: #3b82f6 linear-gradient(0deg, #3b82f6 0%, #3b82f6 100%);
            color: white;
        }
        
        textarea {
            resize: vertical;
            min-height: 110px;
            line-height: 1.5;
        }
        
        .action-buttons {
            margin-top: 2rem;
            text-align: right;
            border-top: 1px solid #e9eef3;
            padding-top: 1.8rem;
        }
        
        .save-btn {
            background: linear-gradient(95deg, #1e3a5f, #0f2b44);
            border: none;
            padding: 0.9rem 2.2rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 2rem;
            color: white;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -12px rgba(0, 0, 0, 0.3);
        }
        
        .alert {
            padding: 1rem 1.2rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 720px) {
            .form-body {
                padding: 1.5rem;
            }
            .field-group {
                flex-direction: column;
            }
            .field-group label {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="form-container">
    <div class="form-header">
        <h1>📄 Регистрационная анкета</h1>
        <p>Заполните данные о себе — все поля проверяются на сервере</p>
    </div>
    
    <div class="form-body">
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ Данные успешно сохранены! Спасибо за регистрацию.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors) && isset($errors['db'])): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($errors['db']); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <!-- ФИО -->
            <div class="field-group">
                <label for="fullname">👤 ФИО *</label>
                <div class="input-wrapper">
                    <input type="text" id="fullname" name="fullname" 
                           value="<?php echo htmlspecialchars($formData['fullname'] ?? ''); ?>"
                           class="<?php echo isset($errors['fullname']) ? 'error' : ''; ?>">
                    <?php if (isset($errors['fullname'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['fullname']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Телефон -->
            <div class="field-group">
                <label for="phone">📞 Телефон</label>
                <div class="input-wrapper">
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                           class="<?php echo isset($errors['phone']) ? 'error' : ''; ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['phone']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Email -->
            <div class="field-group">
                <label for="email">✉️ E-mail *</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                           class="<?php echo isset($errors['email']) ? 'error' : ''; ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['email']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Дата рождения -->
            <div class="field-group">
                <label for="birthdate">🎂 Дата рождения</label>
                <div class="input-wrapper">
                    <input type="date" id="birthdate" name="birthdate" 
                           value="<?php echo htmlspecialchars($formData['birthdate'] ?? ''); ?>"
                           class="<?php echo isset($errors['birthdate']) ? 'error' : ''; ?>">
                    <?php if (isset($errors['birthdate'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['birthdate']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Пол -->
            <div class="field-group">
                <label>⚥ Пол</label>
                <div class="input-wrapper radio-group">
                    <label><input type="radio" name="gender" value="male" <?php echo (isset($formData['gender']) && $formData['gender'] == 'male') ? 'checked' : ''; ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?php echo (isset($formData['gender']) && $formData['gender'] == 'female') ? 'checked' : ''; ?>> Женский</label>
                    <label><input type="radio" name="gender" value="other" <?php echo (isset($formData['gender']) && $formData['gender'] == 'other') ? 'checked' : ''; ?>> Другой</label>
                    <label><input type="radio" name="gender" value="unspecified" <?php echo (!isset($formData['gender']) || $formData['gender'] == 'unspecified') ? 'checked' : ''; ?>> Не указан</label>
                </div>
                <?php if (isset($errors['gender'])): ?>
                    <div class="error-message" style="margin-left: 180px;"><?php echo htmlspecialchars($errors['gender']); ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Языки программирования -->
            <div class="field-group">
                <label>💻 Любимые языки *</label>
                <div class="input-wrapper">
                    <select name="fav_langs[]" id="fav_langs" multiple size="6"
                            class="<?php echo isset($errors['languages']) ? 'error' : ''; ?>">
                        <option value="Pascal" <?php echo (isset($formData['languages']) && in_array('Pascal', $formData['languages'])) ? 'selected' : ''; ?>>Pascal</option>
                        <option value="C" <?php echo (isset($formData['languages']) && in_array('C', $formData['languages'])) ? 'selected' : ''; ?>>C</option>
                        <option value="C++" <?php echo (isset($formData['languages']) && in_array('C++', $formData['languages'])) ? 'selected' : ''; ?>>C++</option>
                        <option value="JavaScript" <?php echo (isset($formData['languages']) && in_array('JavaScript', $formData['languages'])) ? 'selected' : ''; ?>>JavaScript</option>
                        <option value="PHP" <?php echo (isset($formData['languages']) && in_array('PHP', $formData['languages'])) ? 'selected' : ''; ?>>PHP</option>
                        <option value="Python" <?php echo (isset($formData['languages']) && in_array('Python', $formData['languages'])) ? 'selected' : ''; ?>>Python</option>
                        <option value="Java" <?php echo (isset($formData['languages']) && in_array('Java', $formData['languages'])) ? 'selected' : ''; ?>>Java</option>
                        <option value="Haskell" <?php echo (isset($formData['languages']) && in_array('Haskell', $formData['languages'])) ? 'selected' : ''; ?>>Haskell</option>
                        <option value="Clojure" <?php echo (isset($formData['languages']) && in_array('Clojure', $formData['languages'])) ? 'selected' : ''; ?>>Clojure</option>
                        <option value="Prolog" <?php echo (isset($formData['languages']) && in_array('Prolog', $formData['languages'])) ? 'selected' : ''; ?>>Prolog</option>
                        <option value="Scala" <?php echo (isset($formData['languages']) && in_array('Scala', $formData['languages'])) ? 'selected' : ''; ?>>Scala</option>
                        <option value="Go" <?php echo (isset($formData['languages']) && in_array('Go', $formData['languages'])) ? 'selected' : ''; ?>>Go</option>
                    </select>
                    <div class="hint-text" style="font-size:0.7rem; color:#5b6e8c; margin-top:0.3rem;">
                        Удерживайте Ctrl (Cmd) для выбора нескольких языков
                    </div>
                    <?php if (isset($errors['languages'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['languages']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Биография -->
            <div class="field-group">
                <label for="bio">📝 Биография</label>
                <div class="input-wrapper">
                    <textarea id="bio" name="bio" 
                              class="<?php echo isset($errors['biography']) ? 'error' : ''; ?>"><?php echo htmlspecialchars($formData['biography'] ?? ''); ?></textarea>
                    <?php if (isset($errors['biography'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['biography']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Контракт -->
            <div class="field-group">
                <label>📑 Согласие</label>
                <div class="input-wrapper checkbox-wrapper">
                    <input type="checkbox" id="contractCheck" name="contract_agreed" <?php echo (isset($formData['contract']) && $formData['contract'] == 'on') ? 'checked' : ''; ?>>
                    <label for="contractCheck">Я ознакомлен(а) с условиями пользовательского соглашения *</label>
                </div>
                <?php if (isset($errors['contract'])): ?>
                    <div class="error-message" style="margin-left: 180px;"><?php echo htmlspecialchars($errors['contract']); ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Кнопка сохранения -->
            <div class="action-buttons">
                <button type="submit" class="save-btn">💾 Сохранить</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>