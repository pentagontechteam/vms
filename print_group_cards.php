<?php
// File: print_group_cards.php
session_start();

if (!isset($_SESSION['receptionist_id'])) {
    header("Location: vmc_login.php");
    exit();
}

require 'db_connection.php';

$group_id = isset($_GET['group_id']) ? $conn->real_escape_string($_GET['group_id']) : '';

if (empty($group_id)) {
    die("Group ID not provided");
}

$stmt = $conn->prepare("SELECT name, host_name, organization, floor_of_visit, visit_date, is_group_leader 
                      FROM visitors 
                      WHERE group_id = ? 
                      ORDER BY is_group_leader DESC, name ASC");
$stmt->bind_param("s", $group_id);
$stmt->execute();
$result = $stmt->get_result();

$group_members = [];
while ($row = $result->fetch_assoc()) {
    $group_members[] = $row;
}

$stmt->close();
$conn->close();

if (empty($group_members)) {
    die("No group members found");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Visitor Cards - <?= htmlspecialchars($group_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <style>
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }

            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .page-break {
                page-break-after: always;
            }

            .no-print {
                display: none;
            }
        }

        .rotate-text {
            writing-mode: vertical-lr;
            text-orientation: mixed;
            transform: rotate(180deg);
            font-family: 'Montserrat', sans-serif;
        }

        .corner-decoration {
            position: absolute;
            width: 30px;
            height: 30px;
            border-color: #0d9488;
            border-width: 2px;
        }

        .top-left {
            top: 0;
            left: 0;
            border-right: none;
            border-bottom: none;
        }

        .top-right {
            top: 0;
            right: 0;
            border-left: none;
            border-bottom: none;
        }

        .bottom-left {
            bottom: 0;
            left: 0;
            border-right: none;
            border-top: none;
        }

        .bottom-right {
            bottom: 0;
            right: 0;
            border-left: none;
            border-top: none;
        }

        .venue-font {
            font-family: 'Playfair Display', serif;
        }

        .text-font {
            font-family: 'Montserrat', sans-serif;
        }

        .pass-body {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(245, 245, 245, 0.95) 100%);
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="no-print text-center p-4">
        <h1 class="text-2xl font-bold mb-4">Group Visitor Cards - <?= htmlspecialchars($group_id) ?></h1>
        <button onclick="window.print()" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 mr-4">
            Print All Cards
        </button>
        <button onclick="window.close()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
            Close
        </button>
    </div>

    <div class="grid grid-cols-2 gap-4 p-4">
        <?php foreach ($group_members as $index => $member): ?>
            <div class="<?= ($index > 0 && $index % 4 === 0) ? 'page-break' : '' ?>">
                <div class="bg-white w-full h-[500px] shadow-xl flex overflow-hidden relative pass-body mx-auto" style="max-width: 400px;">
                    <!-- Corner Decorations -->
                    <div class="corner-decoration top-left"></div>
                    <div class="corner-decoration top-right"></div>
                    <div class="corner-decoration bottom-left"></div>
                    <div class="corner-decoration bottom-right"></div>

                    <!-- Left Sidebar -->
                    <div class="bg-[#007570] w-16 flex items-center justify-center relative">
                        <div class="rotate-text text-white font-bold text-3xl tracking-wider">
                            VISITOR PASS
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="flex-1 p-6 relative flex flex-col">
                        <!-- Header -->
                        <div class="flex items-center justify-center mb-6">
                            <img src="assets/logo-green-yellow.png" alt="Logo" class="max-w-[180px] h-auto">
                        </div>

                        <!-- Venue Information -->
                        <div class="mb-6 text-center">
                            <div class="text-xl font-bold text-gray-800 mb-2 venue-font">
                                <?= htmlspecialchars($member['floor_of_visit'] ?: 'N/A') ?>
                            </div>
                            <div class="text-lg font-bold text-gray-800 venue-font">
                                <?= $member['is_group_leader'] == 1 ? 'GROUP LEADER' : 'GROUP MEMBER' ?>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="w-3/4 h-[2px] bg-gradient-to-r from-transparent via-[#0d9488] to-transparent mx-auto my-4 rounded-full"></div>

                        <!-- Visitor Details -->
                        <div class="text-font text-sm mb-4">
                            <div class="mb-2"><strong>Name:</strong> <?= htmlspecialchars($member['name']) ?></div>
                            <div class="mb-2"><strong>Organization:</strong> <?= htmlspecialchars($member['organization'] ?: 'N/A') ?></div>
                            <div class="mb-2"><strong>Date:</strong> <?= htmlspecialchars(date('jS F, Y', strtotime($member['visit_date']))) ?></div>
                            <div class="mb-2"><strong>Host:</strong> <?= htmlspecialchars($member['host_name']) ?></div>
                            <div><strong>Group ID:</strong> <?= htmlspecialchars($group_id) ?></div>
                        </div>

                        <!-- Notice -->
                        <div class="mb-6 text-center z-20">
                            <div class="text-sm font-semibold text-gray-800 leading-relaxed text-font">
                                Must be visibly worn at all times while on premises
                            </div>
                        </div>

                        <!-- Fixed Bottom-Right Logo -->
                        <div class="absolute bottom-0 right-0 z-10">
                            <img src="assets/Picture3.png" alt="Bottom Logo" class="h-[6rem] opacity-30" />
                        </div>

                        <!-- Subtle pattern overlay -->
                        <div class="absolute inset-0 opacity-5 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgdmlld0JveD0iMCAwIDYwIDYwIj48cmVjdCB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIGZpbGw9IiNmZmYiLz48cGF0aCBkPSJNMzAgMTVMMTUgMzAgMzAgNDUgNDUgMzB6IiBzdHJva2U9IiMwZDk0ODgiIHN0cm9rZS13aWR0aD0iMS41IiBmaWxsPSJub25lIi8+PC9zdmc+')]"></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(() => {
                if (confirm('Print all group visitor cards now?')) {
                    window.print();
                }
            }, 1000);
        };
    </script>
</body>

</html>