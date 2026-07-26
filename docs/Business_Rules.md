Analiza la base de código de las órdenes y el histórico de las órdenes. Desde las migraciones, factories, seeders,
models, controllers, form requests, api resources, traits, y pruebas tanto unitarias y features tests. Piensa en la
arquitectura necesaria e ideal para solucionar el siguiente requerimiento:
La órden al ser creada debe añadir información acerca de la (s) pieza (s) (ítems) que el cliente está dejando para que
se trabaje en ella. Esta información incluye:
Características generales del motor:
Marca: (Texto)
Litros: (Texto)
Año: (Texto)
Modelo: (Texto)
N° de cilindros: (Texto)
Y puede incluir ninguna, alguna o todas de las siguientes opciones:
Ítems recibidos:
Cabeza: (Boolean)
Tapas de árbol (Boolean)
Tornillos (Boolean)
Barra de balancines (Boolean)
Cuñas (Boolean)
Resortes (Boolean)
Lainas (Boolean)
Válvulas (Boolean)
Guías (Boolean)
Block: (Boolean)
Tapas cojinete (Boolean)
Tornillos de tapas (Boolean)
Árbol (Boolean)
Guías (Boolean)
Metales (Boolean)
Cuña de árbol (Boolean)
Engrane de árbol (Boolean)
Cigüeñal: (Boolean)
Engrane de fierro (Boolean)
Engrane de bronce (Boolean)
Seguro (Boolean)
Cuña (Boolean)
Volante (Boolean)
Tornillo (Boolean)
Deflector (Boolean)
Bielas: (Boolean)
Tornillos (Boolean)
Tuercas (Boolean)
Pistones (Boolean)
Seguros (Boolean)
Metales (Boolean)
Otros: (Boolean)
Bomba de agua (Boolean)
Bomba de aceite (Boolean)
Carter (Boolean)
Sobrecarte (Boolean)
Múltiple admisión (Boolean)
Múltiple escape (Boolean)
Tapas de distribucción (Boolean)
Monto de anticipo: $ (Considera un tipo de campo para manejar dinero)

Al crearse la órden, ésta debe de cambiar de estado de “Recibido” a “Esperando Revisión”. Se debe respetar el envio de
correo actual al cliente dandole información de su órden de trabajo. Además, deberíamos enviar un correo a los usuarios
Super Administradores y Administradores con fines de Auditoría.

En los archivos @work_order_general.jpeg y @work_order_cabeza.jpeg se muestran folios de revisión tanto como cuando el
cliente deja una cabeza a revisón (@work_order_cabeza.jpeg), como el resto de ítems que se pueden recibir se describen
en el @work_order_general.jpeg. Como se puede ver en ambos folios, se incluyen los mismos elementos de la lista de ítems
anterior (Block, Ciguëñal, Bielas y Cabeza), además de otras columnas (Medida (texto), PPTO (Presupuesto) (boolean),
Aut. (Autorizado) (boolean)., T.R (Trabajo Realizado) (boolean). y una columna de texto libre), además de otros campos
como en @work_order_general.jpeg que dice:
Cabeza:
Lavado cabeza 4 Cil Lavado cabezas V6 Lavado cabezas V8 Prueba Hidráulica cabeza 4 cil Prueba Hidráulica cabeza V6/V8
Cepillado cabeza 4 cilindros Cepillado cabezas V6/V8 Soldadura por punto Guía K-Line (P.U.)
Guias Postizas (P.U.)
Rectificado de Asiento (P.U.)
Maquinado de asiento (P.U.)
Encasquillado de asiento (P.U.)
Rectificado de Válvulas (P.U.)
Calibrado 16 vals Armado 16 vals Servicio a buzos Enderezado cabeza Enderezado cabeza diésel Cuerda de birlo Cuerdas de
bujia Cuerda especial de Tritón Block:
Lavado Rectificado por cilindro (P.U.)

        Encamisado por cilindro (P.U.)


        Pulido por cilindro (P.U.)


        Ajuste de bancada (4 cilindros)


        Honeado de bancada (4 cilindros)


        Cepillado 4 cilindros


        Cepillado 6 cilindros


        Cepillado 8 cilindros


        Cepillado block armado 4 cilindros


        Cepillado block armado V6


        Cambio de metales de arbol


        Pulido de árbol o barras balanceadoras


        Soldadura entre cilindros QR25

Cigüeñal:
Pulido cigüeñal (4 cilindros)

        Pulido cigüeñal (6 cilindros)


        Pulido cigüeñal (8 cilindros)


        Rectificado de Cigüeñal (4 cilindros)


        Rectificado de Cigüeñal (6 cilindros)
        Rectificado de Cigüeñal (8 cilindros)
        Enderezado de cigüeñal
        Rellenar ceja
        Rellenar muñón
        Enderezado de cigüeñal
        Rellenar ceja
        Rellenar muñón
        Balanceo Dinámico 4 Cilindros
        Balanceo Dinámico 6 Cilindros
        Balanceo Dinámico 8 Cilindros

Bielas:
Rectificado de bielas (4 bielas)
Ajuste de bielas a cigüeñal (4 bielas)
Armado de pistones a presión (4)
Armado de pistones con seguro (4)
Tórques:
Centro: (Campo de texto libre)
Biela: (Campo de texto libre)
GAP de anillos:
1ra: (Campo de texto libre)
2da: (Campo de texto libre)
3ra: (Campo de texto libre)
Luz de lubricación:
Centro: (Campo de texto libre)
Biela: (Campo de texto libre)

El proceso es el siguiente: El clientre deja una pieza a revisión. Por ejemplo, un Block. Por lo que al crearse la
órden, el encargado selecciona los ítems del Block y, deja a revisión el Árbol, las Tapas de Cojinete y Tornillos de
Tapas. Cuando el encargado selecciona de un combo box en el front-end el Block y, al seleccionar el Block, aparecen las
diferentes opciones relacionadas al block, donde el encargado selecciona el Árbol, las Tapas de Cojinete y Tornillos de
Tapas. Al crearse la órden, ésta pasa al estado “Esperando Revisión”.

Cuando después de un par de días, el encargado revisa las piezas que se dejaron a revisión, dependiendo de la misma el
encargado puede determinar que la pieza necesita los siguientes trabajos detallados en la columna PPTO:
´´´ ———————————————————————————————— | Block | ———————————————————————————————— | Trabajos | Medida | PPTO. | Aut. |
T.R. | ———————————————————————————————— | Lavado | | X | | | | Soldadura entre cilindros QR25 | | X | | | | Cepillado
block armado 4 cilindros | 20 | X | | | | Cambio de metales de arbol | | X | | | | Pulido de árbol o barras
balanceadoras | | X | | | ———————————————————————————————— ´´´ Esto en conjunto con las guías de precios
@price_list_1.jpeg y @price_list_2.jpeg dan el siguiente presupuesto:
Lavado: Precio $600 MXN Precio Neto $696 MXN Soldadura entre cilindros QR25 Precio $800.00 MXN Precio Neto $928.0 MXN
Cepillado block armado 4 cilindros Precio $1,600.00 MXN Precio Neto $1,856.00 MXN Cambio de metales de arbol
Precio $480.00 Precio Neto $556.80 MXN Pulido de árbol o barras balanceadoras Precio $280.00 MXN Precio Neto $324.80 MXN

Por lo que la cotización total quedaría como sigue:
Precio: $3,760 MXN Precio Neto: $4,361.6 MXN

Al terminar este proceso, la órden cambia de estado a “Revisado”, una vez cambiando a estado revisado, la órden debe
enviar un correo al cliente avisándole que su órden esta lista para ser revisara por él y a los usuarios Super
Administradores y Administradores con fines de auditoría. Una vez habiendo enviado el correo al cliente, la órden
inmediatamente debe cambiar de estado a “Esperando aprobación del Cliente”. El cliente puede apruebar o no todas o solo
algunas de las sugerencias por parte del encargado. En nuestro ejemplo, supongamos que el cliente solo aprueba lo
siguiente:

´´´ ———————————————————————————————— | Block | ———————————————————————————————— | Trabajos | Medida | PPTO. | Aut. |
T.R. | ———————————————————————————————— | Lavado | | X | X | | | Soldadura entre cilindros QR25 | | X | X | | |
Cepillado block armado 4 cilindros | 20 | X | | | | Cambio de metales de arbol | | X | X | | | Pulido de árbol o barras
balanceadoras | | X | | | ————————————————————————————————

´´´ Dado que esto es el trabajo AUTORIZADO, entonces la cotización queda como sigue:

Lavado: Precio $600 MXN Precio Neto $696 MXN Soldadura entre cilindros QR25 Precio $800.00 MXN Precio Neto $928.0 MXN
Cambio de metales de arbol Precio $480.00 Precio Neto $556.80 MXN

Por lo que la cotización total ahora quedaría como sigue:
Precio: $1,880 MXN Precio Neto: $2,180.8 MXN

Aquí es muy posible que el encargado actualice el campo de “Monto de anticipo” con el monto que el cliente decida dejar
como anticipo para el trabajo.

Una vez que el cliente aprueba la cotización, la órden cambia de estado a “Listo para trabajo“. El el negocio, los
técnicos pueden hacer el trabajo normalmente de acuerdo a la cotización aprobada por el cliente. Sin embargo, puede
haber ocasiones en las que por alguna razón el personal pueda dañar alguna o varias de las piezas por lo que es posible
que no se cobre ese trabajo. Supongamos en nuestro ejemplo, el técnico dañó parte de la pieza al soldar por lo que ese
trabajo no se cobrará, quedando el trabajo REALIZADO de la siguiente forma:
´´´ ———————————————————————————————— | Block | ———————————————————————————————— | Trabajos | Medida | PPTO. | Aut. |
T.R. | ———————————————————————————————— | Lavado | | X | X | X | | Soldadura entre cilindros QR25 | | X | X | | |
Cepillado block armado 4 cilindros | 20 | X | | | | Cambio de metales de arbol | | X | X | X | | Pulido de árbol o
barras balanceadoras | | X | | | ———————————————————————————————— ´´´ El técnico actualizará la órden para reflejar el
trabajo realizado quedando la cotización final como sigue:

Lavado: Precio $600 MXN Precio Neto $696 MXN Cambio de metales de arbol Precio $480.00 Precio Neto $556.80 MXN

Por lo que la cotización total ahora quedaría como sigue:
Precio: $1,080 MXN Precio Neto: $1,252.8 MXN

Una vez que el técnico termine el trabajo realizado cambiará el estado de la órden a “Listo para entrega” lo cual hace
que se envíe un correo al cliente avisándole que su órden está lista para entregar. Una vez que el cliente recoge su
órden y paga el resto del monto a pagar, la órden es actualizada por el técnico o el encargado para que cambie al estado
“Entregada”. Este cambio de estado envía un correo al cliente y a los usuarios Super Administradores y Administradores
sobre que la órden ha sido entregada finalizando el ciclo de vida de la órden.

Además, una vez que el usuario tenga el número de la órden (UUID) y la fecha de creación de la órden, el usuario debe
ser capaz de acceder a la información de la órden mediante un pequeño formulario (Inertia + Vue) parra poder ver la
información relativa únicamente a esa órden, su histórico y los archivos adjuntos.

Investiga diversas soluciones arquitectónicas que permitan este requerimiento además de no afectar el histórico de las
órdenes. Analiza los pros y contras de cada solución y planifica los pasos para implementar cada una de ellas para
determinar cuál sería la mejor opción.
