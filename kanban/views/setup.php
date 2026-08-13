<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban Setup</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/modal.css">
    <style>
        .setup-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: var(--bg-secondary);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .setup-container h1 {
            text-align: center;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .setup-container p {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: var(--accent-hover);
        }
        .error-message {
            background: #fee;
            color: #c00;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .token-display {
            background: #f0f8ff;
            border: 2px solid #4a9eff;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            word-break: break-all;
        }
        .token-display strong {
            display: block;
            margin-bottom: 10px;
            color: #333;
        }
        .token-display code {
            display: block;
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            user-select: all;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🚀 Установка Kanban</h1>
        <p>Создайте пароль администратора для начала работы</p>

        <?php if (!empty($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="token-display">
            <strong>✅ Администратор создан! Сохраните этот токен:</strong>
            <code><?= htmlspecialchars($adminToken) ?></code>
        </div>
        <div class="warning">
            ⚠️ <strong>Внимание!</strong> Этот токен показывается только один раз. 
            Сохраните его в надёжном месте. Без него вы не сможете войти как администратор.
        </div>
        <?php else: ?>
        <form method="POST" action="/setup">
            <div class="form-group">
                <label for="password">Пароль администратора</label>
                <input type="password" id="password" name="password" required minlength="8" autofocus>
            </div>
            <div class="form-group">
                <label for="confirm_password">Подтвердите пароль</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <button type="submit" class="btn-primary">Инициализировать систему</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
