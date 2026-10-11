# Monitor de foco para cuestionarios Moodle

**Componente:** `quizaccess_focusmonitor`  
**Tipo:** regla de acceso de cuestionario (`quizaccess`)  
**Ruta:** `mod/quiz/accessrule/focusmonitor/`  
**Versión:** `0.6.2-rc2`
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

El menú contextual se cuenta como interacción y puede sugerir revisión de nivel medio. Ninguna cantidad de menús contextuales, por sí sola, produce nivel alto ni se suma a señales fuertes.

### Pregunta y momento de las salidas de foco

En **Mostrar salidas de foco por pregunta y momento** se muestran estudiante, intento, hora de salida y regreso, duración, página, número mostrado de pregunta, posición interna (slot), referencia de la pregunta y tiempo desde el inicio. También se puede exportar esta tabla a CSV. La exportación de eventos añade esas columnas al final, conservando las columnas originales.

Con una pregunta por página se registra esa pregunta. Con varias se conserva la última pregunta tocada o seleccionada; si no hubo interacción se estima la más visible. Esa referencia no demuestra qué estaba leyendo el estudiante. El slot identifica la posición interna de Moodle y puede diferir del número mostrado, especialmente con preguntas aleatorias o de varias partes. No se guarda el texto de preguntas ni las respuestas.

El contexto de las advertencias se conserva desde la salida, aunque se muestre el aviso al regresar. El tiempo transcurrido cuenta desde el inicio del intento, incluyendo pausas, y usa el reloj del servidor como referencia en los eventos nuevos. Los registros antiguos pueden mostrar página y tiempo de recepción, pero no recuperar la pregunta ni corregir un reloj de cliente incorrecto.

Una pérdida de foco seguida de navegación o del fin de los registros se presenta como **Sin retorno confirmado**, sin atribuirle duración. No se empareja con un regreso de otra página. Esos episodios no aumentan el tiempo ni el número de episodios usados para riesgo. Las advertencias explícitas siguen contando. El reporte consulta todos los eventos del cuestionario: se eliminó el recorte silencioso a los últimos 2,000 registros. En cuestionarios con muchísimos eventos la consulta y la tabla completa pueden ser más lentas.

### Advertencias al estudiante

Puede configurarse un umbral de segundos fuera de foco, por ejemplo 10 segundos. Al regresar al examen después de superar ese umbral, el estudiante ve una advertencia y el evento queda registrado.

También puede configurarse un máximo de advertencias visibles. Al alcanzar el máximo, el plugin puede:

1. solo registrar para revisión docente;
2. enviar al estudiante a la pantalla de resumen del intento;
3. enviar al estudiante al resumen y bloquear el regreso a la página de preguntas.

La tercera opción funciona como cierre suave: no entrega automáticamente el intento, pero impide continuar respondiendo después de alcanzar el límite.

## Descarga

Para instalar el plugin en Moodle, descarga el archivo ZIP desde la sección **Releases**:

**Releases → Focus Monitor 0.6.1 RC1 → Assets → quizaccess_focusmonitor_0.6.1-rc1.zip**

Para esta actualización utiliza el paquete `quizaccess_focusmonitor_0.6.2-rc2.zip` entregado junto con los cambios. La existencia de este paquete no implica que se haya publicado una nueva release en GitHub.

No uses el botón **Code → Download ZIP**, ya que ese archivo corresponde al código fuente del repositorio y no necesariamente al paquete instalable para Moodle.

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

Para el piloto con advertencias sin bloqueo, elige **Solo registrar para revisión docente**. Puedes configurar **5 segundos** en el campo existente; esta actualización conserva los umbrales, máximos y acciones guardados y mantiene 10 segundos como valor predeterminado. El aviso se muestra al regresar al examen, cuando el navegador vuelve a estar visible.

## Actualización y prueba de 0.6.2 RC2

Actualizar desde 0.6.1 RC1 o 0.6.2 RC1 no cambia el esquema de base de datos ni elimina eventos o ajustes. Instala el ZIP como actualización y purga cachés. No desinstales el plugin.

RC2 corrige tres problemas revelados por la prueba en celular:

- Navegar y volver a un documento guardado en la caché del navegador podía conservar una salida antigua o generar avisos en la pregunta siguiente. Ahora se descartan esos marcadores y se distingue la navegación del mismo documento.
- Algunos eventos de salida no llegaban al servidor, aunque sí llegaba la advertencia de regreso. Se usa `sendBeacon` para enviar las salidas cuando el navegador lo permite, con respaldo XHR si rechaza la cola. El reporte puede reconstruir un episodio desde una advertencia válida y lo etiqueta **Reconstruido desde advertencia**. No lo duplica si existen los dos extremos ni recupera advertencias asociadas a navegación. Esto es evidencia del contador del cliente, no dos eventos brutos completos.
- Los relojes de cliente y servidor podían mostrar horas distintas en las dos tablas. Los eventos nuevos incluyen una hora anclada al reloj del servidor. Para registros RC1 se corrige el reloj usando la carga de cada documento, el inicio del intento y el tiempo transcurrido guardado. Los logs anteriores sin esas referencias mantienen la limitación del reloj del cliente.

Los registros de advertencias ya mostradas se conservan: corregir su interpretación no borra retroactivamente los avisos ni el máximo alcanzado. Los CSV de pruebas con nombres, correos e IP no forman parte del código publicado.

Antes de usarlo en un examen real, prueba en un cuestionario de práctica desde celular:

1. Configura 5 segundos y acción Solo registrar. Una salida de 4 segundos no debe advertir; una de 6 debe mostrar aviso al regresar. Repite con 10 segundos y verifica que una salida de 6 no advierta.
2. Navega normalmente entre preguntas, incluso con una carga lenta. No debe generar una advertencia ni unir una salida de una página con un regreso de otra.
3. Revisa número mostrado, página, tiempo y duración en la tabla de salidas y en ambos CSV detallados. Con varias preguntas verifica la referencia de última interacción o visibilidad.
4. Abre menús contextuales sin salir del examen: se cuentan como interacción, sin producir riesgo alto por sí solos.
5. En un intento separado, verifica que los modos Enviar a resumen y Bloquear regreso sigan funcionando al alcanzar el máximo configurado.

Las pruebas locales cubren el script emitido, navegación y restauración desde caché, envío de eventos, relojes desfasados, episodios reconstruidos, exportaciones completas y actualización sin cambios de esquema. No sustituyen una nueva prueba de RC2 en tu instancia Moodle 3.3.9+ ni en el navegador de los estudiantes. Tampoco se certifica aquí compatibilidad con una futura migración a Moodle 5.x.

## Uso responsable

El plugin debe presentarse al estudiante como una herramienta de monitoreo de integridad. Se recomienda informar que el examen registra cambios de foco y acciones como copiar/pegar.

No se recomienda usar el reporte como sanción automática. La interpretación debe considerar el contexto: notificaciones del sistema, errores técnicos, recargas, accesibilidad, tipo de dispositivo y supervisión presencial.

## Notas técnicas

- Moodle 3.3 limita los nombres de campos de base de datos a 30 caracteres. Las versiones futuras deben evitar nombres largos en `install.xml` y `upgrade.php`.
- Después de actualizar el plugin, siempre purgar cachés antes de probar.
- No desinstalar el plugin para resolver errores de actualización si se desean conservar logs; primero intentar reemplazar la carpeta del código.

## Estado

Esta versión se considera **release candidate para piloto interno**, no versión final institucional.
