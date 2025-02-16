# Fix It - Smart Car Service Booking System

A modern web application for booking and managing car service appointments with AI-driven service recommendations.

## 🚀 Features

### For Customers
- **User Account Management**
  - Secure registration and login
  - Profile management
  - Multi-vehicle management
  - Service history tracking

- **Smart Service Booking**
  - AI-powered service recommendations based on vehicle history
  - Real-time appointment scheduling
  - Service status tracking
  - Automated maintenance reminders

- **Vehicle Management**
  - Add multiple vehicles
  - Track maintenance history
  - View service schedules
  - Mileage tracking

- **Notifications System**
  - Service reminders
  - Appointment confirmations
  - Status updates
  - Maintenance alerts

### For Service Providers
- **Admin Dashboard**
  - Service schedule management
  - Customer management
  - Service history tracking
  - Performance analytics

- **Service Management**
  - Service package customization
  - Pricing management
  - Availability scheduling
  - Resource allocation

## 🛠 Technology Stack

- **Frontend**
  - HTML5, CSS3, JavaScript
  - Bootstrap 5
  - jQuery
  - AJAX

- **Backend**
  - PHP 8.2
  - MySQL/MariaDB
  - PDO for database operations

- **Security**
  - CSRF protection
  - SQL injection prevention
  - XSS protection
  - Password hashing
  - Session management

## 📋 Prerequisites

- PHP >= 8.2
- MySQL/MariaDB >= 10.4
- Apache/Nginx web server
- Composer (for dependency management)
- Web browser with JavaScript enabled

## 🔧 Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/car-service-booking.git
   cd car-service-booking
   ```

2. **Configure the database**
   ```bash
   # Import the database schema
   mysql -u your_username -p your_database_name < car_service_booking.sql
   ```

3. **Configure the application**
   ```bash
   # Copy the example config file
   cp app/config/config.example.php app/config/config.php
   
   # Edit the configuration file with your settings
   nano app/config/config.php
   ```

4. **Set up the web server**
   - Configure your web server to point to the `public` directory
   - Ensure the `logs` directory is writable

5. **Set up file permissions**
   ```bash
   chmod 755 -R public/
   chmod 777 -R logs/
   ```

6. **Start using the application**
   - Visit `http://your-domain.com/register.php` to create an account
   - Log in and start managing your vehicles and service bookings

## 📚 Documentation

### Database Schema
The application uses the following main tables:
- `users` - User account information
- `cars` - Vehicle information
- `services` - Available service packages
- `bookings` - Service appointments
- `service_history` - Past service records
- `service_recommendations` - AI-generated service suggestions

### Key Files
- `/public` - Web-accessible files
- `/app/config` - Configuration files
- `/app/models` - Database models
- `/app/controllers` - Business logic
- `/app/views` - UI templates

## 🔐 Security Features

- Password hashing using bcrypt
- CSRF token protection
- Prepared statements for database queries
- Input validation and sanitization
- Session security measures
- XSS protection
- Rate limiting for login attempts

## 💡 AI Service Recommendations

The system uses the following factors to generate service recommendations:
- Vehicle age and mileage
- Manufacturer-recommended service intervals
- Previous service history
- Driving patterns
- Seasonal maintenance requirements

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📜 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## 👥 Authors

- Your Name - *Initial work* - [YourGithub](https://github.com/yourusername)

## 🙏 Acknowledgments

- Bootstrap team for the frontend framework
- PHP community for excellent documentation
- All contributors who have helped with testing and improvements

## 📧 Contact

For support or queries, please email: support@your-domain.com

## 🔄 Roadmap

Future planned features:
- Mobile app integration
- Payment gateway integration
- Service provider mobile app
- Advanced analytics dashboard
- Integration with vehicle OBD systems
- Customer loyalty program
