<?php

use App\Config\Database;
use App\Controllers\BulletinController;
use App\Controllers\GradeController;
use App\Controllers\StudentController;
use App\Controllers\ClassController;
use App\Controllers\ParentController;
use App\Controllers\SubjectController;

// Connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Initialisation des contrôleurs
$bulletinController = new BulletinController($db);
$gradeController = new GradeController($db);
$studentController = new StudentController($db);
$classController = new ClassController($db);
$subjectController = new SubjectController($db);
$parentController = new ParentController($db);

// Récupération des données de l'étudiant
$studentId = $_GET['student_id'] ?? null; // Récupérer l'ID de l'étudiant depuis l'URL ou autre source
if (!$studentId) {
    echo "Aucun étudiant sélectionné.";
    exit;
}

$bulletinId = $bulletinController->readBulletinByStudent($studentId)->id;

$student = $studentController->readStudentWithUsersInformations($studentId);
if (!$student) {
    echo "L'étudiant n'existe pas.";
    exit;
}

$subjects = $subjectController->readSubjectByLevel($classController->readClass($student->class_id)->level);

// Extraire les id des subjects
$SubjectIds = array_map(fn($subject) => $subject->id, $subjects);

$parentEmail = $parentController->readParent($student->parent_id)->email;

// Récupération des notes de l'étudiant
$grades = $gradeController->readAllGradesByStudent($studentId);
if (empty($grades)) {
    echo "L'étudiant n'a pas de notes disponibles pour générer le bulletin.";
    exit;
}

// Extraire tous les subject_id
$gradeSubjectIds = array_map(fn($grade) => $grade->subject_id, $grades);

// Obtenir les subject_id uniques
$uniqueSubjectIds = array_unique($gradeSubjectIds);

// Vérifier si tous les subject IDs des subjects sont dans les grades
$missingIds = array_diff($SubjectIds, $gradeSubjectIds);

// if (empty($missingIds)) {
//     echo "Tous les sujets sont présents dans les grades.\n";
// } else {
//     echo "Les IDs suivants ne sont pas dans les grades : " . implode(', ', $missingIds) . "\n";
// }

$finalGrades = [];

foreach ($grades as $grade) {
    $subject_id = $grade->subject_id;
    $type_note = $grade->type_note;
    $gradeValue = $grade->grade;

    if (!isset($finalGrades[$subject_id])) {
        $finalGrades[$subject_id] = [
            'cc' => 0,
            'tp' => 0,
            'exam' => 0,
            'rattrapage' => 0,
        ];
    }

    if (isset($finalGrades[$subject_id][$type_note])) {
        $finalGrades[$subject_id][$type_note] = $gradeValue;
    }
}

// Calculer la note finale pour chaque matière
$subjectFinalGrades = [];

foreach ($finalGrades as $subject_id => $notes) {
    $cc = $notes['cc'];
    $tp = $notes['tp'];
    $exam = $notes['exam'];
    $rattrapage = $notes['rattrapage'];

    // Remplacer la note d'examen par la note de rattrapage si elle est plus élevée
    if ($rattrapage > $exam) {
        $exam = $rattrapage;
    }

    // Calculer la note finale pondérée
    $finalGrade = ($cc * 0.2) + ($tp * 0.4) + ($exam * 0.4);
    $subjectFinalGrades[$subject_id] = $finalGrade;
}

$niveau = $classController->readClass($student->class_id)->level;

// Calcul de la moyenne générale
$average = array_sum($subjectFinalGrades) / count($subjectFinalGrades);

// Détermination de la mention
function getMention($average) {
    if ($average == 0) 
        return 'Excellent';
    elseif ($average >= 16) 
        return 'Très Bien';
    elseif ($average >= 14) 
        return 'Bien';
    elseif ($average >= 12) 
        return 'Assez Bien';
    elseif ($average >= 10) 
        return 'Passable';
    else 
        return 'Insuffisant';
    
}

$mention = getMention($average);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin de Notes</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');

        :root {
            --primary-color: #1e40af;
            --secondary-color: #f97316;
            --background-color: #f3f4f6;
            --text-color: #1f2937;
            --border-color: #e5e7eb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 90rem;
            margin: 40px auto;
            padding: 20px;
        }

        .bulletin {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            color: white;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            width: 100px;
            height: auto;
        }

        .title {
            font-size: 28px;
            font-weight: 700;
        }

        .student-info {
            padding: 30px;
            background-color: #f9fafb;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }

        .student-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 30px;
            border: 4px solid white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .student-details {
            flex-grow: 1;
            text-align: start;
        }

        .student-details p {
            margin-bottom: 10px;
            font-size: 16px;
        }

        .grades {
            padding: 30px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 1px;
        }

        tr:hover td {
            background-color: #e5e7eb;
            transform: scale(1.02);
        }

        td:first-child, th:first-child {
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }

        td:last-child, th:last-child {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .summary {
            background-color: #f9fafb;
            padding: 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary p {
            font-size: 18px;
            font-weight: 600;
        }

        .mention {
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 24px;
        }

        .signature {
            padding: 20px;
            text-align: right;
            font-style: italic;
            color: #6b7280;
            border-top: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .logo {
                margin-bottom: 20px;
            }

            .student-info {
                flex-direction: column;
                text-align: center;
            }

            .student-photo {
                margin-right: 0;
                margin-bottom: 20px;
            }

            .summary {
                flex-direction: column;
            }

            .summary p {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
<?php if(empty($missingIds)) : ?>
            <center>
                <button id="downloadPdf" class="add-button">Télécharger le Bulletin</button>
                <!-- <button id="sendEmailButton" class="add-button" style="background-color: var(--primary-color);">Envoyer par e-mail</button> -->
            </center>
            <?php endif ?>
    <div class="container">
        <div class="bulletin">
            <header class="header">
                <img src="../images/keyce_logo.png" alt="Logo de l'école" class="logo">
                <h1 class="title">Bulletin de Notes</h1>
            </header>
            <div class="student-info">
                <div class="student-details">
                    <p><strong>Nom:</strong> <?= $student->first_name . ' ' . $student->last_name ?></p>
                    <p><strong>Classe:</strong> <?= $niveau ?></p>
                    <p><strong>Matricule:</strong> <?= $student->matricule ?></p>
                </div>
                <img src="<?= htmlspecialchars($user->profile_picture ?? '/images/profiles/default_profile.jpg'); ?>" alt="Photo de l'étudiant" class="student-photo">
            </div>
            <div class="grades">
                <table>
                    <thead>
                        <tr>
                            <th data-translate="subject">Matière</th>
                            <th data-translate="grade">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectFinalGrades as $subject_id => $finalGrade): ?>
                        <tr>
                            <td><?= $subjectController->readSubject($subject_id)->name ?></td>
                            <td><?= number_format($finalGrade, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="summary">
                <p><strong>Moyenne Générale:</strong> <?= number_format($average, 2) ?></p>
                <p><strong>Mention:</strong> <span class="mention"><?= htmlspecialchars($mention) ?></span></p>
            </div>
            <div class="signature">
                <p>Signature numérique: </p>
                <img width="100" height="100" src="/images/signature.png" alt="code qr">
            </div>
                <div class="container alert alert-danger">
                    <?php
                        if(!empty($missingIds)){
                            echo "Les notes de toutes les matières n'ont pas été renseignées.";
                            $missingNames = [];
                            foreach ($missingIds as $id) {
                                $missingNames[] = $subjectController->readSubject($id)->name;
                            }
                            echo "  Les matières restantes sont " . implode(", ", $missingNames) ."";
                        }
                    ?>
                </div>
            
        </div>
    </div>
</body>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsPDF/2.5.1/jspdf.umd.min.js"></script>

<script>
   document.getElementById('downloadPdf').addEventListener('click', async () => {
    const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const element = document.querySelector('.container');

        html2canvas(element, {
            useCORS: true,
            logging: true,
            allowTaint: true,
        }).then(async function (canvas) {
            const imgData = canvas.toDataURL('image/jpeg', 1.0);
            doc.addImage(imgData, 'JPEG', 10, 10, 180, 0);
        try{
            // Convertir le PDF en blob
            const pdfBlob = doc.output('blob');
        console.log('PDF généré :', pdfBlob);

        // Envoyer le PDF au serveur
        const formData = new FormData();
        formData.append('pdf', pdfBlob, 'bulletin.pdf');
        formData.append('parent_email', 'emmanuelscre1@gmail.com');
        formData.append('student_email', 'plazarecrute@gmail.com');

        const response = await fetch('/sign_bulletin', {
            method: 'POST',
            body: formData
        });
        const responseText = await response.text();
        console.log('reponse brute du serveur :', responseText);
        console.log('Statut de la réponse :', response.status);
        const result = await response.json();
        console.log('Réponse du serveur :', result);

        if (response.ok && result.success) {
            alert('Le bulletin signé a été généré avec succès.');
            const downloadLink = document.createElement('a');
            downloadLink.href = result.file_url;
            downloadLink.download = 'bulletin_signed.pdf';
            downloadLink.click();
        } else {
            alert(`Erreur lors de la signature : ${result.message}`);
        }
    } catch (error) {
        console.error('Erreur lors de l\'envoi ou de la génération :', error);
        alert('Une erreur est survenue lors de l\'envoi ou de la génération du bulletin.');
    }
})});

</script>


</html>
