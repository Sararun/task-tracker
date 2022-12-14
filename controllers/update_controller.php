<?php
/** @var $PDODriver */

if ($_POST['mode'] === 'updated') {
    $data = [];
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['csrf_token', 'mode'])) {
            continue;
        }
        $data[$key] = htmlspecialchars(strip_tags(trim($value)));
    }

    $errors = [];

    validFields($data, $errors);

    $id = $_GET['id'] ?? 0;

    if (!empty($errors)) {
        $_SESSION['any'] = $errors;
        $_SESSION['data'] = [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    } else {
        $query = "SELECT * FROM tasks WHERE id=:id LIMIT 1";
        $sth = $PDODriver->prepare($query);
        $sth->execute([
            ':id' => $id,
        ]);
        $item = $sth->fetch();

        if (empty($item)) {
            throw new \PDOException("Page not found (#404) ", 404);
        }

        $data['id'] = $id;
        $data['user_id'] = $data['user_id'] ?? $_SESSION['user']['id'];
        $data['updated_at'] = date('Y-m-d H:i:s');

        $columns = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            $columns[] = "{$key}=:{$key}";
            $params[":{$key}"] = $value;
        }

        try {
            $query = "UPDATE tasks SET "
                . implode(', ', $columns)
                . " WHERE id=:id LIMIT 1";

            $sth = $PDODriver->prepare($query);
            $sth->execute($params);
        } catch (\PDOException $e) {
            throw new PDOException("SQL: {$query}", 500);
        }

        if ($sth->rowCount() > 0) {
            $_SESSION['success'] = 'Успешно сохранено.';
        } else {
            $_SESSION['error'] = 'Данные не менялись.';
        }
    }

    redirect("edit?id={$id}");
} else {
    throw new \PDOException("Page not found (#404) ", 404);
}
