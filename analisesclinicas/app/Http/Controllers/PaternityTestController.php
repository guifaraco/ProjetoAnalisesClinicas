<?php

namespace App\Http\Controllers;

use App\Models\AlleleFreq;
use App\Models\PaternityTest;
use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Error;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use LaravelLegends\PtBrValidator\Rules\Cpf;

use function PHPUnit\Framework\throwException;

class PaternityTestController extends Controller
{
    public function index()
    {
        return Inertia::render('PaternityTest/Index');
    }

    public function select()
    {
        return Inertia::render('PaternityTest/Select');
    }

    public function create_duo()
    {
        $patients = Patient::join('users', 'patients.user_id', '=', 'users.id')
            ->select('patients.id', 'users.id as user_id', 'users.name as patient_name', 'users.cpf')
            ->get();

        return Inertia::render('PaternityTest/CreateDuo', ['patients' => $patients]);
    }

    public function create_trio()
    {
        $patients = Patient::join('users', 'patients.user_id', '=', 'users.id')
            ->select('patients.id', 'users.id as user_id', 'users.name as patient_name', 'users.cpf')
            ->get();

        return Inertia::render('PaternityTest/CreateTrio', ['patients' => $patients]);
    }

    public function store(Request $request, $type)
    {

        $request->validate([
            'cpf' => 'required|cpf|formato_cpf',
            'lab' => 'required',
            'health_insurance' => 'required|not_in:0',
            'exam_date' => 'required',
            'description' => 'required',
        ]);
        try {
            $patient = Patient::join('users', 'patients.user_id', '=', 'users.id')->select('patients.id', 'users.id as user_id', 'users.name', 'users.cpf')->where('users.cpf', $request->cpf)->firstOrFail();


            $formated_participants_list = [];

            foreach ($request->participants as $participant) {
                array_push($formated_participants_list, $participant['cpf']['value']);
            }
            if (count($formated_participants_list) == 0) {
                throw new Exception('Não foi adicionado nenhum participante.');
            }
            $participants_json = json_encode($formated_participants_list);

            $patient->paternityTests()->create([
                'participants' => $participants_json,
                'health_insurance' => $request->health_insurance,
                'exam_date' => $request->exam_date,
                'lab' => $request->lab,
                'description' => $request->description,
            ]);

            return redirect()->route('paternity.index')->with("message", "Pedido cadastrado com sucesso.");
        } catch (Exception $e) {

            $patients = Patient::join('users', 'patients.user_id', '=', 'users.id')
                ->select('patients.id', 'users.id as user_id', 'users.name as patient_name', 'users.cpf')
                ->get();
            if ($type == 'duo') {
                return Inertia::render('PaternityTest/CreateDuo', [
                    'error' => $e->getMessage() == "Não foi adicionado nenhum participante." ? $e->getMessage() : 'Não foi possível salvar o novo pedido.',
                    'patients' => $patients,
                ]);
            }
            if ($type == 'trio') {
                return Inertia::render('PaternityTest/CreateTrio', [
                    'error' => $e->getMessage() == "Não foi adicionado nenhum participante." ? $e->getMessage() : 'Não foi possível salvar o novo pedido.',
                    'patients' => $patients,
                ]);
            }
        }
    }

    public function search(Request $request)
    {

        $auth = Auth::user();
        $result = [];
        $query = PaternityTest::join('patients', 'paternity_tests.patient_id', '=', 'patients.id')
            ->join('users', 'patients.user_id', '=', 'users.id')
            ->select('paternity_tests.*', 'users.cpf', 'users.name as patient_name');

        if ($auth->hasRole(['patient'])) {
            $result = $query
                ->where('users.cpf', $auth->cpf)->orderBy('paternity_tests.updated_at', 'desc')->get();
        } else {
            if ($request->search == "") {
                $result = $query->orderBy('paternity_tests.updated_at', 'desc')->get();
            } else {
                $patient = Patient::join('users', 'patients.user_id', '=', 'users.id')
                    ->select('users.cpf')->where('users.cpf', $request->search)->get();

                $result = $query
                    ->where('users.cpf', $request->search)->orderBy('paternity_tests.updated_at', 'desc')->get();

                if (count($patient) == 0) {
                    return [
                        "result" => $result,
                        "status" => "patient not found"
                    ];
                }
            }
        }

        if (count($result) == 0) {
            return [
                "result" => $result,
                "status" => "exams is empty",
            ];
        }

        return [
            "result" => $result,
            "status" => "ok",
        ];
    }

    public function edit($id)
    {

        $paternityTest = PaternityTest::find($id);

        return Inertia::render('PaternityTest/Edit', ['paternityTest' => $paternityTest]);
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'lab' => 'required',
                'health_insurance' => 'required|not_in:0',
                'exam_date' => 'required',
                'description' => 'required',
            ]);

            PaternityTest::find($id)->update([
                'health_insurance' => $request->health_insurance,
                'exam_date' => $request->exam_date,
                'lab' => $request->lab,
                'description' => $request->description,
            ]);

            return redirect()->route('paternity.index')->with("message", "Dados do pedido atualizados com sucesso.");
        } catch (Exception $e) {
            $paternityTest = PaternityTest::find($id);

            return Inertia::render('PaternityTest/Edit', ["error" => "Não foi possível realizar a atualização dos dados.", 'paternityTest' => $paternityTest]);
        }
    }

    public function report_manage($id)
    {
        $paternityTest = PaternityTest::find($id);

        return Inertia::render('PaternityTest/ReportManage', ['paternityTest' => $paternityTest]);
    }

    public function create_duo_report($id)
    {
        $paternityTest = PaternityTest::find($id);

        return Inertia::render('PaternityTest/CreateReportDuo', ['paternityTest' => $paternityTest]);
    }


    public function create_trio_report($id)
    {
        $paternityTest = PaternityTest::find($id);

        return Inertia::render('PaternityTest/CreateReportTrio', ['paternityTest' => $paternityTest]);
    }



    public function calc_ipc_trio(Request $request, $id)
    {
        $exclusion = 0;
        $allelesFreq = AlleleFreq::all();
        $ip = [];
        foreach ($request->loci as $locus => $value) {
            $allele1_mother = (float) str_replace(',', '.', $value["mae_alelo1"]);
            $allele2_mother = (float) str_replace(',', '.', $value["mae_alelo2"]);
            $allele1_child = (float) str_replace(',', '.', $value["crianca_alelo1"]);
            $allele2_child = (float) str_replace(',', '.', $value["crianca_alelo2"]);
            $allele1_father = (float) str_replace(',', '.', $value["pai_alelo1"]);
            $allele2_father = (float) str_replace(',', '.', $value["pai_alelo2"]);
            $mother_and_child_different_alleles = $allele1_mother == $allele1_child && $allele2_mother == $allele2_child;

            if ($allele1_father != $allele2_father) {
                $paternal_allele = 0;
                if ($allele1_father == $allele1_child || $allele1_father == $allele2_child) {
                    $paternal_allele =  $allele1_father;
                }
                if ($allele2_father == $allele1_child || $allele2_father == $allele2_child) {
                    $paternal_allele =  $allele1_father;
                }
                if ($paternal_allele == 0) {
                    $ip[$locus] = 1;
                    $exclusion++;
                }

                if (!$mother_and_child_different_alleles && $paternal_allele != 0) {
                    foreach ($allelesFreq as $allele) {
                        if ($allele['Alelo'] == $paternal_allele) {
                            $ip[$locus] = 0.5 / $allele[$locus];
                        }
                    }
                } else {
                    $allele1_freq = 0;
                    $allele2_freq = 0;
                    foreach ($allelesFreq as $allele) {
                        if ($allele1_child == $allele['Alelo']) {
                            $allele1_freq = $allele[$locus];
                        }
                        if ($allele2_child == $allele['Alelo']) {
                            $allele2_freq = $allele[$locus];
                        }
                    }
                    $ip[$locus] = 0.5 / ($allele1_freq + $allele2_freq);
                }
            } else {
                $paternal_allele = 0;
                if ($allele1_father == $allele1_child || $allele1_father == $allele2_child) {
                    $paternal_allele =  $allele1_father;
                }
                if ($allele2_father == $allele1_child || $allele2_father == $allele2_child) {
                    $paternal_allele =  $allele1_father;
                }
                if ($paternal_allele == 0) {
                    $ip[$locus] = 1;
                    $exclusion++;
                }
                if (!$mother_and_child_different_alleles && $paternal_allele != 0) {
                    foreach ($allelesFreq as $allele) {
                        if ($allele['Alelo'] == $paternal_allele) {
                            $ip[$locus] = 1 / $allele[$locus];
                        }
                    }
                } else {
                    $allele1_freq = 0;
                    $allele2_freq = 0;
                    foreach ($allelesFreq as $allele) {
                        if ($allele1_child == $allele['Alelo']) {
                            $allele1_freq = $allele[$locus];
                        }
                        if ($allele2_child == $allele['Alelo']) {
                            $allele2_freq = $allele[$locus];
                        }
                    }
                    $ip[$locus] = 1 / ($allele1_freq + $allele2_freq);
                }
            }
        }
        $ipa = array_product($ip);
        $pp = $ipa / ($ipa + 1) * 100;

        $paternityTest = PaternityTest::find($id);
        return Inertia::render('PaternityTest/PreviewPdf', ['ipa' => $ipa, 'pp' => $pp, 'exclusion' => $exclusion, 'ip' => $ip, 'loci' => $request->loci, 'paternityTest' => $paternityTest]);
    }

    public function calc_ipc_duo(Request $request) {}

    public function store_report(Request $request, $id)
    {
        $paternityTest = PaternityTest::join('patients', 'patients.id', '=', 'paternity_tests.patient_id')
            ->join('users', 'users.id', '=', 'patients.user_id')
            ->where('paternity_tests.id', $id)
            ->select('users.name as patient_name')
            ->first();

        try {
            $pdf = Pdf::loadview('pdf.paternity_report', ['ipa' => $request->ipa, 'pp' => $request->pp, 'ip' => $request->ip, 'loci' => $request->loci, 'conclusion' => $request->conclusion]);

            $current_date = Carbon::now()->format('d-m-Y');
            $file_name = $id . "-" . $paternityTest->patient_name . '-laudo-' . $current_date . '.pdf';
            $file_path = 'laudos/paternidade/' . $file_name;

            Storage::put($file_path, $pdf->output());


            PaternityTest::find($id)
                ->updateOrFail([
                    'pdf' => $file_path,
                    'state' => 'Finalizado'
                ]);

            return redirect()->route('paternity.report.manage')->with("message", "Sucesso ao gerar o Pdf.");
        } catch (Exception $e) {
            dd($e->getMessage());
            $paternityTest = PaternityTest::find($id);
            return Inertia::render('PaternityTest/PreviewPdf', ['ipa' => $request->ipa, 'pp' => $request->pp, 'exclusion' => $request->exclusion, 'ip' => $request->ip, 'loci' => $request->loci, 'paternityTest' => $paternityTest, 'error' => "Erro ao gerar o Pdf."]);
        }
    }

    public function download_report($id)
    {
        $paternityTest = PaternityTest::find($id)->first();
        try {
            return Storage::download($paternityTest->pdf);
        } catch (Error $error) {
            return Inertia::render('PaternityTest/ReportManage', ['paternityTest' => $paternityTest, 'error' => 'Não foi possível fazer o download, não existe nenhum laudo nesse pedido']);
        }
    }

    public function remove_report($id)
    {
        $paternityTest = PaternityTest::find($id);
        try {
            Storage::delete($paternityTest->pdf);
            PaternityTest::find($id)
                ->updateOrFail([
                    'pdf' => null,
                    'state' => 'analisando'
                ]);
            return redirect()->route('paternity.report.manage')->with("message", "Sucesso ao remover o Pdf.");
        } catch (Error $error) {
            return Inertia::render('PaternityTest/ReportManage', ['paternityTest' => $paternityTest, 'error' => 'Não foi possível remover o laudo, não existe nenhum laudo nesse pedido.']);
        }
    }
}
