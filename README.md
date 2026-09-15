# DrBank - Plataforma de Preparación para Exámenes Médicos

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/AI-Groq-000000?style=for-the-badge&logo=groq" alt="Groq AI">
  <img src="https://img.shields.io/badge/Cloud-GCP-4285F4?style=for-the-badge&logo=google-cloud" alt="Google Cloud">
  <img src="https://img.shields.io/badge/Firebase-FFCA28?style=for-the-badge&logo=firebase" alt="Firebase">
</p>

## 📋 Descripción

**DrBank** es una plataforma educativa avanzada para la preparación de exámenes médicos especializados, principalmente el ENAM (Examen Nacional de Aspirantes a Residencias Médicas) en Perú. Es un sistema de aprendizaje inteligente basado en preguntas de exámenes anteriores con integración de IA para personalizar el estudio de los estudiantes de medicina.

### Características Principales

- 🎯 **Banco de Preguntas**: Acceso a preguntas de exámenes anteriores con justificaciones detalladas
- 🤖 **Recomendaciones con IA**: Análisis personalizado usando Groq Llama 3.3 70b
- 📊 **Planes de Estudio Inteligentes**: Generación automática de planes semanales personalizados
- 📱 **Firebase Cloud Messaging**: Sistema completo de notificaciones push en tiempo real
- 📈 **Seguimiento de Progreso**: Historial detallado de rendimiento por tema
- 🏆 **Sistema de Ranking**: Competencia amigable entre estudiantes
- 🔐 **Autenticación Social**: Login con Google, Facebook y Apple
- 📧 **Email Automatizado**: Sistema de notificaciones vía Google Cloud Pub/Sub
- 📄 **Generación de PDF**: Resúmenes de exámenes descargables
- 🌍 **Multiidioma**: Soporte para múltiples idiomas

## 🛠️ Stack Tecnológico

### Core
- **Framework**: Laravel 12.0
- **PHP**: 8.2+
- **Base de Datos**: SQLite (por defecto) / MySQL / MariaDB

### Dependencias Principales
- `laravel/sanctum` - Autenticación de API con tokens
- `laravel/socialite` - Autenticación social (Google, Facebook, Apple)
- `firebase/php-jwt` - Manejo de tokens JWT
- `barryvdh/laravel-dompdf` - Generación de PDFs
- `darkaonline/l5-swagger` - Documentación API OpenAPI/Swagger
- `google/cloud-pubsub` - Colas de mensajes asíncronos
- `google/apiclient` - Cliente de Google APIs
- `inspector-apm/neuron-ai` - Servicio de IA (Groq/OpenAI compatible)

### Servicios Externos
- **Google Cloud Pub/Sub** - Colas de mensajes asíncronos
- **Firebase Cloud Messaging (FCM)** - Sistema completo de notificaciones push
- **Groq API** - IA para recomendaciones (Llama 3.3 70b)
- **Google OAuth** - Autenticación social
- **Facebook OAuth** - Autenticación social
- **Apple Sign-in** - Autenticación social

## 📦 Instalación

### Requisitos Previos
- PHP 8.2 o superior
- Composer
- Node.js y NPM
- Cuenta de Google Cloud (para Pub/Sub y Firebase)
- Cuenta de Groq (para IA)

### Pasos de Instalación

1. **Clonar el repositorio**
```bash
git clone <repository-url>
cd drbank
```

2. **Instalar dependencias**
```bash
composer install
npm install
```

3. **Configurar environment**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurar variables de entorno**
```env
APP_NAME=DrBank
APP_ENV=local
APP_KEY=your-app-key
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# o DB_CONNECTION=mysql para MySQL

# Google Cloud
GOOGLE_CLOUD_PROJECT=your-project-id
GOOGLE_CLOUD_KEY_FILE_PATH=storage/app/google/your-credentials.json

# Firebase Cloud Messaging (FCM)
FIREBASE_PROJECT_ID=your-firebase-project
FIREBASE_SERVICE_ACCOUNT_PATH=storage/app/google/your-credentials.json

# Groq AI
GROQ_API_KEY=your-groq-api-key

# Social Login
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
FACEBOOK_CLIENT_ID=your-facebook-client-id
FACEBOOK_CLIENT_SECRET=your-facebook-client-secret
APPLE_CLIENT_ID=your-apple-client-id
APPLE_TEAM_ID=your-apple-team-id
APPLE_KEY_FILE_ID=your-apple-key-file-id
APPLE_PRIVATE_KEY=your-apple-private-key
```

5. **Ejecutar migraciones**
```bash
php artisan migrate
```

6. **Iniciar servidor de desarrollo**
```bash
npm run dev
```

Esto iniciará:
- Servidor Laravel (`php artisan serve`)
- Cola de trabajos (`php artisan queue:listen`)
- Logs en tiempo real (`php artisan pail`)
- Vite para assets frontend

## 📁 Estructura del Proyecto

```
drbank/
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php          # Autenticación y perfil
│   │   ├── QuestionController.php      # Sistema de preguntas
│   │   ├── StudentProgressController.php # Progreso del estudiante
│   │   └── FeedbackController.php      # Sistema de soporte
│   ├── Models/
│   │   ├── Client.php                  # Modelo de usuario
│   │   ├── Question.php                # Modelo de preguntas
│   │   ├── Exam.php                    # Modelo de exámenes
│   │   └── ...
│   ├── Services/
│   │   ├── GoogleQueue.php             # Servicio Pub/Sub
│   │   ├── FirebaseNotificationService.php # Notificaciones
│   │   ├── PdfGenerator.php            # Generación de PDFs
│   │   └── NeuronAIServices.php        # Servicio de IA
│   └── Mail/
│       ├── ActivationProfileMail.php  # Email de activación
│       ├── RecoveryPasswordMail.php   # Email de recuperación
│       └── DownloadExamSummaryMail.php # Resumen de examen
├── routes/
│   ├── api.php                         # Rutas API principales
│   ├── auth/auth.php                   # Rutas de autenticación
│   ├── profile/profile.php             # Rutas de perfil
│   ├── quiz/quiz.php                   # Rutas de preguntas
│   └── external/external.php           # Rutas externas
├── database/
│   └── migrations/                     # Migraciones de base de datos
└── storage/
    └── app/google/                     # Credenciales de Google
```

## 🔌 Endpoints API

### Autenticación
```
POST   /api/v1/auth/register           # Registro de usuario
POST   /api/v1/auth/login              # Iniciar sesión
POST   /api/v1/auth/refresh            # Refresh token
POST   /api/v1/auth/activation         # Activar cuenta
POST   /api/v1/auth/recovery           # Iniciar recuperación de contraseña
POST   /api/v1/auth/recovery-validation # Validar token de recuperación
POST   /api/v1/auth/recovery-password  # Generar nueva contraseña
POST   /api/v1/auth/resend-activation  # Reenviar código de activación
POST   /api/v1/auth/social             # Login social
GET    /api/v1/auth/me                 # Obtener perfil
POST   /api/v1/auth/logout             # Cerrar sesión
DELETE /api/v1/auth/delete             # Eliminar cuenta
```

### Perfil de Usuario
```
POST /api/v1/profile/notifications     # Configurar notificaciones
POST /api/v1/profile/update            # Actualizar perfil
POST /api/v1/profile/change-password   # Cambiar contraseña
```

### Sistema de Preguntas
```
POST /api/v1/quiz/questions            # Obtener preguntas con filtros
POST /api/v1/quiz/by-year              # Filtrar por año y tipo de examen
POST /api/v1/quiz/question/theme       # Preguntas por tema
GET  /api/v1/quiz/exam-type            # Listar tipos de exámenes
GET  /api/v1/quiz/specialty            # Listar especialidades
GET  /api/v1/quiz/area                 # Listar áreas médicas
GET  /api/v1/quiz/theme                # Listar temas
GET  /api/v1/quiz/year                 # Listar años disponibles
POST /api/v1/quiz/report               # Reportar pregunta
POST /api/v1/quiz/history              # Guardar historial
GET  /api/v1/quiz/history              # Obtener historial
POST /api/v1/quiz/ranking              # Registrar puntaje
GET  /api/v1/quiz/ranking              # Obtener ranking
POST /api/v1/quiz/exam                 # Registrar examen
PATCH /api/v1/quiz/exam/status         # Actualizar estado de examen
GET  /api/v1/quiz/exam                 # Obtener exámenes del usuario
POST /api/v1/quiz/exam/download-summary # Descargar resumen por email
```

### Progreso del Estudiante
```
GET  /api/v1/student/progress          # Obtener progreso
POST /api/v1/student/mark-topic-studied # Marcar tema como estudiado
```

### Soporte
```
POST /api/v1/external/support          # Enviar feedback
```

### Webhook
```
POST /pubsub-endpoint                  # Endpoint Pub/Sub
```

## 🚀 Comandos de Consola

### Generación de Planes de Estudio
```bash
php artisan student:plan
```
Genera planes de estudio semanales personalizados basados en el rendimiento del estudiante.

### Recomendaciones con IA
```bash
php artisan student:recommendation
```
Genera recomendaciones personalizadas de estudio usando IA (Groq Llama 3.3 70b).

## 📚 Documentación API

La documentación completa de la API está disponible vía Swagger/OpenAPI:

```
http://localhost:8000/swagger/drbank
```

## 🔐 Seguridad

- **Autenticación**: Tokens Sanctum con expiración configurable
- **Rate Limiting**: Throttling por endpoint para prevenir abuso
- **Validación de Contenido**: Middleware `CheckBadWords` para contenido inapropiado
- **Encriptación**: Respuestas de preguntas encriptadas
- **HTTPS**: Recomendado para producción

## 🌍 Características Especiales

### Plan de Estudio Inteligente
- Algoritmo basado en Índice de Prioridad (IP)
- Prioriza temas donde el estudiante ha cometido errores
- Distribución: 4 temas por día (lunes a sábado)
- Domingo: repaso general de la semana

### Recomendaciones con IA
- Análisis de resultados de exámenes
- Recomendaciones personalizadas en español
- Respuestas bajo 500 palabras
- Notificaciones push cuando están listas

### Sistema de Notificaciones con Firebase
- **Firebase Cloud Messaging (FCM)**: Sistema completo de notificaciones push
- Procesamiento en batches de 100 tokens para manejar grandes volúmenes
- OAuth2 para autenticación con Google Cloud
- Almacenamiento de tokens FCM en tabla `client_firebases`
- Notificaciones personalizadas por dispositivo
- Soporte para notificaciones masivas a múltiples estudiantes

### Multiidioma
- Soporte para múltiples idiomas
- Parámetro `?lang=` en todos los endpoints
- Traducciones configurables en `lang/`

## 🗄️ Base de Datos

### Tablas Principales
- `clients` - Usuarios del sistema
- `questions` - Banco de preguntas de exámenes
- `themes` - Temas organizadores
- `specialties` - Especialidades médicas
- `areas` - Áreas médicas principales
- `exam_type` - Tipos de exámenes (ENAM, etc.)
- `exams` - Registros de exámenes realizados
- `history` - Historial de rendimiento
- `social_profiles` - Perfiles sociales
- `student_study_plans` - Planes de estudio
- `student_daily_progress` - Progreso diario
- `feedback` - Retroalimentación de usuarios

## 🧪 Testing

```bash
# Ejecutar tests
composer test

# Ejecutar tests con Pail (logs en tiempo real)
npm run dev
```

## 📝 Notas de Desarrollo

- **Zona Horaria**: Todas las fechas están configuradas en `America/Lima`
- **Arquitectura Cloud-Native**: Usa Google Cloud Pub/Sub en lugar de colas Laravel estándar
- **Configuración Regional**: Optimizado para Perú (exámenes ENAM)
- **Credenciales**: Archivo de credenciales en `storage/app/google/`

## 🤝 Contribuyendo

1. Fork el repositorio
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto es software de código abierto licenciado bajo la [MIT license](https://opensource.org/licenses/MIT).

## 📞 Soporte

Para soporte técnico o preguntas, puedes:
- Abrir un issue en el repositorio
- Enviar un email al equipo de desarrollo
- Usar el endpoint `/api/v1/external/support` para feedback

---

Desarrollado con ❤️ usando Laravel 12.0