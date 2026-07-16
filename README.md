# Monitor de foco para cuestionarios Moodle

**Componente:** `quizaccess_focusmonitor`  
**Tipo:** regla de acceso de cuestionario (`quizaccess`)  
**Ruta:** `mod/quiz/accessrule/focusmonitor/`  
**Versión:** `0.6.1-rc1`  
**Compatibilidad mínima:** Moodle 3.3 (`2017051500`)  
**Licencia:** GNU GPL v3 o posterior

## Propósito

Este plugin registra señales del navegador durante intentos de cuestionarios Moodle para apoyar la revisión docente de integridad académica en exámenes presenciales. No pretende demostrar automáticamente conducta indebida ni sustituir el criterio del profesor.

La herramienta está pensada para escenarios donde el estudiante realiza el examen en un solo dispositivo supervisado. En ese contexto, cambiar de pestaña, cambiar de aplicación, copiar, pegar o abrir menús contextuales puede generar señales útiles para revisión.

## Qué registra

El plugin puede registrar eventos como:

- carga de página;
- navegación entre preguntas;
- pérdida y recuperación de foco;
- cambio de visibilidad de la página;
- copiar, pegar y cortar;
- menú contextual/clic derecho o pulsación larga;
- intento de impresión;
- tecla PrintScreen cuando el navegador la expone;
- advertencias mostradas al estudiante;
- límite de advertencias alcanzado.

## Qué no puede detectar de forma confiable

El plugin no puede detectar de forma confiable:

- uso de otro dispositivo externo;
- captura de pantalla hecha desde botones físicos o gestos del sistema móvil;
- cámara externa;
- conversación presencial;
- IA usada en otro aparato;
- intención del estudiante.

Por tanto, el reporte debe tratarse como evidencia orientativa, no como veredicto automático.

## Funciones principales

### Reporte docente

El reporte muestra una tabla principal con:

- estudiante;
- correo;
- calificación final;
- eventos totales;
- eventos de navegación;
- episodios fuera de foco;
- tiempo fuera de foco;
- tiempo total del examen;
- porcentaje fuera del examen;
- advertencias visibles alcanzadas;
- copiar, pegar y menú contextual;
- señales fuertes;
- nivel de riesgo sugerido;
- último evento.

Los eventos detallados quedan ocultos en una sección desplegable para depuración.

### Niveles de riesgo

El nivel de riesgo es una sugerencia para revisión docente. No significa que el plugin haya comprobado trampa.

Criterios generales:

- **Bajo:** sin señales relevantes.
- **Medio:** señales menores o episodios moderados fuera de foco.
- **Alto:** señales fuertes, tiempo fuera de foco relevante, varias salidas de foco o límite de advertencias alcanzado.

### Advertencias al estudiante

Puede configurarse un umbral de segundos fuera de foco, por ejemplo 10 segundos. Al regresar al examen después de superar ese umbral, el estudiante ve una advertencia y el evento queda registrado.

También puede configurarse un máximo de advertencias visibles. Al alcanzar el máximo, el plugin puede:

1. solo registrar para revisión docente;
2. enviar al estudiante a la pantalla de resumen del intento;
3. enviar al estudiante al resumen y bloquear el regreso a la página de preguntas.

La tercera opción funciona como cierre suave: no entrega automáticamente el intento, pero impide continuar respondiendo después de alcanzar el límite.

## Instalación

1. Subir el ZIP desde:

   `Administración del sitio → Plugins → Instalar plugins`

2. Confirmar instalación o actualización desde:

   `Administración del sitio → Notificaciones`

3. Purgar cachés después de actualizar:

   `Administración del sitio → Desarrollo → Purgar todas las cachés`

La purga de cachés es importante porque Moodle puede conservar versiones anteriores de JavaScript, idioma o metadatos del plugin. Sin purgar caché, es posible probar código viejo aunque el ZIP nuevo ya esté instalado.

## Configuración recomendada para piloto

En un cuestionario de prueba:

- Activar monitoreo: **Sí**
- Modo de advertencia: **Mostrar advertencias al estudiante**
- Segundos fuera de foco para advertir: **10**
- Máximo de advertencias visibles: **3**
- Acción al superar el máximo: **Enviar a resumen y bloquear regreso al intento**

## Uso responsable

El plugin debe presentarse al estudiante como una herramienta de monitoreo de integridad. Se recomienda informar que el examen registra cambios de foco y acciones como copiar/pegar.

No se recomienda usar el reporte como sanción automática. La interpretación debe considerar el contexto: notificaciones del sistema, errores técnicos, recargas, accesibilidad, tipo de dispositivo y supervisión presencial.

## Notas técnicas

- Moodle 3.3 limita los nombres de campos de base de datos a 30 caracteres. Las versiones futuras deben evitar nombres largos en `install.xml` y `upgrade.php`.
- Después de actualizar el plugin, siempre purgar cachés antes de probar.
- No desinstalar el plugin para resolver errores de actualización si se desean conservar logs; primero intentar reemplazar la carpeta del código.

## Estado

Esta versión se considera **release candidate para piloto interno**, no versión final institucional.
