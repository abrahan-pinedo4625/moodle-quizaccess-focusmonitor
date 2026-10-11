<?php
/**
 * Spanish strings for quizaccess_focusmonitor.
 *
 * @package    quizaccess_focusmonitor
 * @copyright  2026 Abrahan Pinedo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Monitor de foco';
$string['focusmonitorheader'] = 'Monitor de foco';
$string['focusmonitorenabled'] = 'Activar monitoreo de foco';
$string['focusmonitorenabled_help'] = 'Si se activa, Moodle registrará eventos básicos del navegador durante el intento del cuestionario, como cambios de pestaña, pérdida de foco, copiar, pegar, clic derecho, cambios de pantalla completa e intentos de cierre o recarga. Esto no impide la copia; solo registra señales para revisión docente.';
$string['focusmonitorshowwarnings'] = 'Modo de advertencia al estudiante';
$string['focusmonitorshowwarnings_help'] = 'Si se activan las advertencias visibles, el estudiante verá un aviso al iniciar el intento y una advertencia cuando regrese al cuestionario después de permanecer fuera de foco más tiempo que el umbral configurado.';
$string['warningssilent'] = 'Solo registro silencioso';
$string['warningsvisible'] = 'Mostrar advertencias al estudiante';
$string['descriptionstudent'] = 'Este cuestionario tiene monitoreo de foco activado. Los cambios de foco del navegador y eventos relacionados pueden registrarse para revisión académica.';
$string['studentnotice'] = 'El monitoreo de foco está activo en este cuestionario. Salir de la ventana del examen, cambiar de pestaña, copiar, pegar o usar el menú contextual puede quedar registrado.';
$string['returnwarning'] = 'Se detectó que saliste del examen durante {duration} segundos. Advertencia {count} de {max}. Este evento quedó registrado.';
$string['viewreport'] = 'Ver reporte del monitor de foco';
$string['reporttitle'] = 'Reporte del monitor de foco';
$string['reportintro'] = 'Este reporte lista eventos del navegador registrados durante los intentos. Es evidencia para revisión, no una prueba automática de conducta indebida.';
$string['reportinterpretationnote'] = 'Interpretación: las cargas de página y los eventos cercanos al cambio de pregunta se clasifican como navegación normal. El porcentaje fuera del examen se calcula con base en el tiempo total registrado del examen, mostrado en la tabla. Las señales fuertes, como copiar, pegar, cortar, imprimir, tecla de captura en computadora o abrir menú contextual, requieren revisión docente.';
$string['norecords'] = 'No se han registrado eventos del monitor de foco para este cuestionario.';
$string['summary'] = 'Resumen por estudiante';
$string['attemptsummary'] = 'Resumen por intento';
$string['events'] = 'Eventos registrados';
$string['student'] = 'Estudiante';
$string['eventcount'] = 'Eventos totales';
$string['riskcount'] = 'Eventos de riesgo';
$string['navigationevents'] = 'Eventos de navegación';
$string['focusrawcount'] = 'Cambios de foco brutos';
$string['focuslosscount'] = 'Pérdidas de foco';
$string['focusepisodes'] = 'Episodios fuera de foco';
$string['focustime'] = 'Tiempo fuera de foco';
$string['strongevents'] = 'Señales fuertes';
$string['copycount'] = 'Copiar';
$string['pastecount'] = 'Pegar';
$string['cutcount'] = 'Cortar';
$string['contextmenucount'] = 'Clic derecho / menú';
$string['printcount'] = 'Imprimir';
$string['risklevel'] = 'Nivel de riesgo';
$string['risklow'] = 'Bajo';
$string['riskmedium'] = 'Medio';
$string['riskhigh'] = 'Alto';
$string['eventcategory'] = 'Categoría';
$string['categorynavigation'] = 'Navegación normal';
$string['categorynavigationnoise'] = 'Foco asociado a navegación';
$string['categoryfocus'] = 'Pérdida/regreso de foco';
$string['categorystrong'] = 'Señal fuerte';
$string['categorytechnical'] = 'Técnico';
$string['lasttime'] = 'Último evento';
$string['attemptid'] = 'ID del intento';
$string['eventtype'] = 'Tipo de evento';
$string['eventdata'] = 'Datos del evento';
$string['ipaddress'] = 'Dirección IP';
$string['missingcmidorquizid'] = 'Falta el ID del módulo de curso o el ID del cuestionario.';
$string['warninglimitreached'] = 'Has alcanzado el límite de advertencias configurado; tu intento será enviado a revisión docente.';
$string['focusmonitorwarningthreshold'] = 'Segundos fuera de foco para advertir';
$string['focusmonitorwarningthreshold_help'] = 'Número de segundos continuos fuera del foco del examen necesarios para mostrar una advertencia al estudiante y registrar el evento focus_warning. Valor recomendado inicial: 10 segundos.';
$string['focusmonitormaxwarnings'] = 'Máximo de advertencias visibles';
$string['focusmonitormaxwarnings_help'] = 'Número de advertencias visibles antes de ejecutar la acción configurada. Se recomienda probar primero en exámenes de práctica.';
$string['focuspercent'] = 'Porcentaje fuera del examen';
$string['warningcount'] = 'Advertencias visibles';
$string['screenshotcount'] = 'Captura / tecla';
$string['categorywarning'] = 'Advertencia del monitor';
$string['filterstudent'] = 'Filtrar estudiante';
$string['filterstudentplaceholder'] = 'Nombre o correo';
$string['filterrisk'] = 'Nivel de riesgo';
$string['filterallrisks'] = 'Todos';
$string['showattemptsummary'] = 'Mostrar resumen por intento';
$string['showrawevents'] = 'Mostrar eventos detallados para depuración';
$string['finalgrade'] = 'Calificación final';

$string['attemptduration'] = 'Tiempo total del examen';
$string['exportsummarycsv'] = 'Exportar resumen CSV';
$string['exporteventscsv'] = 'Exportar eventos CSV';
$string['sortinstruction'] = 'Haz clic en cualquier encabezado de la tabla para ordenar ascendente o descendente.';
$string['focusmonitoraction'] = 'Acción al superar el máximo de advertencias';
$string['focusmonitoraction_help'] = 'Define qué ocurre cuando el estudiante alcanza el máximo de advertencias. "Solo registrar" conserva el intento abierto. "Enviar a pantalla de resumen" saca al estudiante de la página de preguntas. "Bloquear regreso al intento" además impide volver a la página de preguntas después de alcanzar el límite. No equivale a una entrega automática definitiva.';
$string['actionreviewonly'] = 'Solo registrar para revisión docente';
$string['actionsummary'] = 'Enviar a pantalla de resumen del intento';
$string['actionblock'] = 'Enviar a resumen y bloquear regreso al intento';
$string['strictmodereredirecting'] = 'Serás enviado a la pantalla de resumen del intento.';
$string['strictmodeblockredirecting'] = 'Serás enviado al resumen y no podrás regresar a la página de preguntas.';
$string['strictblockmessage'] = 'Este intento alcanzó el límite de advertencias del monitor de foco. El regreso a la página de preguntas quedó bloqueado y el intento debe ser enviado o revisado por el docente.';

$string['categoryinteraction'] = 'Interacción con menú contextual';
$string['eventpage'] = 'Página del examen';
$string['eventquestion'] = 'Pregunta mostrada';
$string['eventslot'] = 'Posición interna (slot)';
$string['contextsource'] = 'Referencia de la pregunta';
$string['elapsedtime'] = 'Tiempo desde inicio del intento';
$string['elapsedseconds'] = 'Segundos desde inicio del intento';
$string['context_only_question'] = 'Única pregunta de la página';
$string['context_last_interaction'] = 'Última pregunta tocada o seleccionada';
$string['context_viewport'] = 'Pregunta más visible (estimación)';
$string['context_unknown'] = 'Pregunta no identificada';
$string['showepisodes'] = 'Mostrar salidas de foco por pregunta y momento';
$string['exportepisodecsv'] = 'Exportar salidas de foco CSV';
$string['episodestart'] = 'Salida de foco';
$string['episodeend'] = 'Regreso al foco';
$string['episodeduration'] = 'Duración fuera de foco';
$string['episodedurationseconds'] = 'Duración fuera de foco (segundos)';
$string['episodestatus'] = 'Estado del episodio';
$string['episodeconfirmed'] = 'Retorno registrado';
$string['episodeunconfirmed'] = 'Sin retorno confirmado';
$string['episodereconstructed'] = 'Reconstruido desde advertencia';
$string['episodecontextnote'] = 'La pregunta corresponde al contexto registrado al salir. En páginas con varias preguntas se muestra la última interacción o una estimación de visibilidad; no demuestra qué estaba leyendo el estudiante. Los registros antiguos conservan página y tiempo cuando están disponibles, pero no permiten recuperar la pregunta. Las salidas sin retorno confirmado se muestran sin duración y no aumentan el tiempo ni los episodios usados para calcular el riesgo. Abrir un menú contextual por sí solo no produce riesgo alto.';
