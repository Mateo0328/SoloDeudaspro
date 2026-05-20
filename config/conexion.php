<?php
$host = "localhost";
$db = "solodeudas";
$user = "root";
$pass = "";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->exec("SET NAMES 'utf8mb4'");

    try {
        $conexion->query("SELECT requiere_cambio_password FROM prestamista LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE prestamista ADD COLUMN requiere_cambio_password TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT rol FROM prestamista LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE prestamista ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'usuario'");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT intentos_fallidos FROM prestamista LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE prestamista ADD COLUMN intentos_fallidos INT(11) NOT NULL DEFAULT 0");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT bloqueado_hasta FROM prestamista LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE prestamista ADD COLUMN bloqueado_hasta DATETIME DEFAULT NULL");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT cedula FROM cliente LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE cliente ADD COLUMN cedula VARCHAR(30) DEFAULT NULL");
        } catch (Exception $e2) {
        }
        try {
            $conexion->exec("CREATE UNIQUE INDEX uq_cliente_cedula ON cliente(cedula)");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT estado FROM cliente LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE cliente ADD COLUMN estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT contacto_estado FROM cliente LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE cliente ADD COLUMN contacto_estado ENUM('vigente','no_vigente') NOT NULL DEFAULT 'vigente'");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT usuario_responsable FROM historial_estado_prestamo LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE historial_estado_prestamo ADD COLUMN usuario_responsable INT(11) DEFAULT NULL");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT id_edicion FROM ediciones_prestamo LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("CREATE TABLE IF NOT EXISTS ediciones_prestamo (
                id_edicion INT(11) NOT NULL AUTO_INCREMENT,
                id_prestamo INT(11) NOT NULL,
                solicitado_por INT(11) NOT NULL,
                fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                cambios TEXT NOT NULL,
                estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
                revisado_por INT(11) DEFAULT NULL,
                fecha_revision DATETIME DEFAULT NULL,
                motivo TEXT DEFAULT NULL,
                last_update DATETIME DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (id_edicion),
                KEY idx_ediciones_prestamo (id_prestamo, estado),
                KEY idx_ediciones_solicitante (solicitado_por, estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Exception $e2) {
        }
    }

    try {
        $correoAdmin = "admin@solodeudas.com";
        $hash = password_hash("Admin1234", PASSWORD_DEFAULT);

        $correoExists = $conexion->prepare("SELECT id_prestamista FROM prestamista WHERE correo = ? LIMIT 1");
        $correoExists->execute([$correoAdmin]);
        $adminId = $correoExists->fetchColumn();

        if(!$adminId){
            $ins = $conexion->prepare("INSERT INTO prestamista (nombre, correo, contrasena, estado) VALUES (?, ?, ?, 'activo')");
            $ins->execute(["Administrador", $correoAdmin, $hash]);
            $adminId = (int)$conexion->lastInsertId();
        }

        try {
            $up = $conexion->prepare("UPDATE prestamista SET contrasena = ?, estado = 'activo' WHERE id_prestamista = ?");
            $up->execute([$hash, (int)$adminId]);
        } catch (Exception $e2) {
        }

        try {
            $up = $conexion->prepare("UPDATE prestamista SET rol='admin' WHERE id_prestamista = ?");
            $up->execute([(int)$adminId]);
        } catch (Exception $e2) {
        }

        try {
            $up = $conexion->prepare("UPDATE prestamista SET intentos_fallidos=0, bloqueado_hasta=NULL WHERE id_prestamista = ?");
            $up->execute([(int)$adminId]);
        } catch (Exception $e2) {
        }

        try {
            $up = $conexion->prepare("UPDATE prestamista SET requiere_cambio_password=0 WHERE id_prestamista = ?");
            $up->execute([(int)$adminId]);
        } catch (Exception $e2) {
        }
    } catch (Exception $e) {
    }

    try {
        $conexion->query("SELECT id_sesion FROM sesiones_usuario LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("CREATE TABLE IF NOT EXISTS sesiones_usuario (
                id_sesion INT(11) NOT NULL AUTO_INCREMENT,
                id_prestamista INT(11) NOT NULL,
                session_id VARCHAR(128) NOT NULL,
                estado ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
                ip_usuario VARCHAR(100) DEFAULT NULL,
                navegador VARCHAR(255) DEFAULT NULL,
                inicio_sesion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                ultima_actividad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                cierre_sesion DATETIME DEFAULT NULL,
                motivo_cierre VARCHAR(100) DEFAULT NULL,
                last_update DATETIME DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
                PRIMARY KEY (id_sesion),
                KEY idx_sesiones_usuario (id_prestamista, estado),
                KEY idx_sesiones_session (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Exception $e2) {
        }
    }

    try {
        $triggerExiste = $conexion->prepare("SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_pago_prestamo_activo' LIMIT 1");
        $triggerExiste->execute();
        if(!$triggerExiste->fetchColumn()){
            $conexion->exec("CREATE TRIGGER trg_pago_prestamo_activo
                BEFORE INSERT ON pago
                FOR EACH ROW
                BEGIN
                    DECLARE est VARCHAR(20);
                    SELECT estado INTO est FROM prestamo WHERE id_prestamo = NEW.id_prestamo LIMIT 1;
                    IF est IS NULL OR est <> 'activo' THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN09A: El pago debe estar asociado a un préstamo activo';
                    END IF;
                END");
        }
    } catch (Exception $e) {
    }

    try {
        $conexion->query("SELECT confirmado FROM pago LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN confirmado TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Exception $e2) {
        }
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN confirmado_por INT(11) DEFAULT NULL");
        } catch (Exception $e2) {
        }
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN confirmado_fecha DATETIME DEFAULT NULL");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT estado_pago FROM pago LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN estado_pago ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente'");
        } catch (Exception $e2) {
        }
        try {
            $conexion->exec("UPDATE pago SET estado_pago = CASE WHEN confirmado = 1 THEN 'validado' ELSE 'pendiente' END WHERE estado_pago IS NULL");
        } catch (Exception $e2) {
        }
    }
    try {
        $col = $conexion->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pago' AND COLUMN_NAME = 'estado_pago' LIMIT 1");
        $col->execute();
        $colType = (string)($col->fetchColumn() ?? '');
        if($colType !== '' && stripos($colType, "'anulado'") === false){
            $conexion->exec("ALTER TABLE pago MODIFY COLUMN estado_pago ENUM('pendiente','validado','rechazado','anulado') NOT NULL DEFAULT 'pendiente'");
        }
    } catch (Exception $e) {
    }

    try {
        $conexion->query("SELECT registrado_por FROM pago LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN registrado_por INT(11) DEFAULT NULL");
        } catch (Exception $e2) {
        }
        try {
            $conexion->exec("UPDATE pago pa JOIN prestamo pr ON pa.id_prestamo = pr.id_prestamo SET pa.registrado_por = pr.id_prestamista WHERE pa.registrado_por IS NULL");
        } catch (Exception $e2) {
        }
    }

    try {
        $conexion->query("SELECT anulado_por FROM pago LIMIT 1");
    } catch (Exception $e) {
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN anulado_por INT(11) DEFAULT NULL");
        } catch (Exception $e2) {
        }
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN anulado_fecha DATETIME DEFAULT NULL");
        } catch (Exception $e2) {
        }
        try {
            $conexion->exec("ALTER TABLE pago ADD COLUMN motivo_anulacion TEXT DEFAULT NULL");
        } catch (Exception $e2) {
        }
    }

    try {
        $triggerExiste = $conexion->prepare("SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_pago_no_modificar_confirmado' LIMIT 1");
        $triggerExiste->execute();
        if($triggerExiste->fetchColumn()){
            $conexion->exec("DROP TRIGGER IF EXISTS trg_pago_no_modificar_confirmado");
        }
        $conexion->exec("CREATE TRIGGER trg_pago_no_modificar_confirmado
            BEFORE UPDATE ON pago
            FOR EACH ROW
            BEGIN
                IF OLD.confirmado = 1 THEN
                    IF NOT (
                        OLD.estado_pago = 'validado' AND NEW.estado_pago = 'anulado'
                        AND NEW.id_prestamo = OLD.id_prestamo
                        AND NEW.fecha_pago = OLD.fecha_pago
                        AND NEW.monto_pagado = OLD.monto_pagado
                        AND NEW.forma_pago <=> OLD.forma_pago
                        AND NEW.recibido_por <=> OLD.recibido_por
                        AND NEW.observacion <=> OLD.observacion
                        AND NEW.registrado_por <=> OLD.registrado_por
                        AND NEW.confirmado = OLD.confirmado
                        AND NEW.confirmado_por <=> OLD.confirmado_por
                        AND NEW.confirmado_fecha <=> OLD.confirmado_fecha
                        AND NEW.anulado_por IS NOT NULL
                        AND NEW.anulado_fecha IS NOT NULL
                        AND NEW.motivo_anulacion IS NOT NULL
                        AND NEW.motivo_anulacion <> ''
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN11A: El pago no puede modificarse después de confirmarse';
                    END IF;
                END IF;
            END");
    } catch (Exception $e) {
    }

    try {
        $triggerExiste = $conexion->prepare("SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_pago_no_modificar_finalizado' LIMIT 1");
        $triggerExiste->execute();
        if($triggerExiste->fetchColumn()){
            $conexion->exec("DROP TRIGGER IF EXISTS trg_pago_no_modificar_finalizado");
        }
        $conexion->exec("CREATE TRIGGER trg_pago_no_modificar_finalizado
            BEFORE UPDATE ON pago
            FOR EACH ROW
            BEGIN
                IF OLD.estado_pago IN ('validado','rechazado','anulado') THEN
                    IF NOT (
                        OLD.estado_pago = 'validado' AND NEW.estado_pago = 'anulado'
                        AND NEW.id_prestamo = OLD.id_prestamo
                        AND NEW.fecha_pago = OLD.fecha_pago
                        AND NEW.monto_pagado = OLD.monto_pagado
                        AND NEW.forma_pago <=> OLD.forma_pago
                        AND NEW.recibido_por <=> OLD.recibido_por
                        AND NEW.observacion <=> OLD.observacion
                        AND NEW.registrado_por <=> OLD.registrado_por
                        AND NEW.confirmado = OLD.confirmado
                        AND NEW.confirmado_por <=> OLD.confirmado_por
                        AND NEW.confirmado_fecha <=> OLD.confirmado_fecha
                        AND NEW.anulado_por IS NOT NULL
                        AND NEW.anulado_fecha IS NOT NULL
                        AND NEW.motivo_anulacion IS NOT NULL
                        AND NEW.motivo_anulacion <> ''
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN11A/RN12A: El pago no puede modificarse después de validarse, rechazarse o anularse';
                    END IF;
                END IF;
            END");
    } catch (Exception $e) {
    }
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
