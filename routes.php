<?php

declare(strict_types=1);

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/TicketController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/UnitController.php';
require_once __DIR__ . '/controllers/ReportController.php';

Flight::route('/', ['AuthController', 'showHome']);
Flight::route('GET /report', ['TicketController', 'reportForm']);
Flight::route('POST /report', ['TicketController', 'submitReport']);

Flight::route('GET /login', ['AuthController', 'showLogin']);
Flight::route('POST /login', ['AuthController', 'postLogin']);

Flight::route('/dashboard', ['DashboardController', 'showDashboard']);
Flight::route('/dashboard/updates', ['DashboardController', 'dashboardUpdates']);
Flight::route('POST /dashboard/filter', ['DashboardController', 'setFilter']);

Flight::route('/tickets', ['DashboardController', 'listTickets']);

Flight::route('GET /ticket/@id', ['TicketController', 'viewTicket']);
Flight::route('GET /ticket/@id/notify', ['TicketController', 'notifyClient']);
Flight::route('POST /ticket/@id/notify', ['TicketController', 'notifyClient']);
Flight::route('POST /ticket/@id/priority', ['TicketController', 'setPriority']);
Flight::route('POST /ticket/@id/assign', ['TicketController', 'assign']);
Flight::route('POST /ticket/@id/reassign', ['TicketController', 'reassign']);
Flight::route('POST /ticket/@id/start', ['TicketController', 'start']);
Flight::route('POST /ticket/@id/finish', ['TicketController', 'finish']);
Flight::route('POST /ticket/@id/close', ['TicketController', 'close']);
Flight::route('POST /ticket/@id/confirm', ['TicketController', 'confirm']);
Flight::route('POST /ticket/@id/reopen', ['TicketController', 'reopen']);
Flight::route('POST /ticket/@id/delete', ['TicketController', 'deleteTicket']);

Flight::route('/users', ['UserController', 'list']);
Flight::route('GET /user/create', ['UserController', 'showCreate']);
Flight::route('POST /user/create', ['UserController', 'create']);
Flight::route('GET /user/@id/edit', ['UserController', 'showEdit']);
Flight::route('POST /user/@id/edit', ['UserController', 'update']);
Flight::route('POST /user/@id/reset', ['UserController', 'resetPassword']);
// Allow logged-in users (staff) to change their own password
Flight::route('GET /user/change-password', ['UserController', 'showChangePassword']);
Flight::route('POST /user/change-password', ['UserController', 'changePassword']);

Flight::route('/units', ['UnitController', 'list']);
Flight::route('GET /unit/create', ['UnitController', 'showCreate']);
Flight::route('POST /unit/create', ['UnitController', 'create']);

Flight::route('/reports', ['ReportController', 'charts']);
Flight::route('/reports/data', ['ReportController', 'data']);
Flight::route('/reports/export', ['ReportController', 'export']);
Flight::route('GET /reports/sanity', ['ReportController', 'sanity']);

Flight::route('/uploads/*', ['TicketController', 'serveUpload']);

Flight::route('/logout', ['AuthController', 'logout']);
