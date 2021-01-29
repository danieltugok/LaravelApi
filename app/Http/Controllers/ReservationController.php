<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

use App\Models\Area;
use App\Models\Unit;
use App\Models\AreaDisabledDay;
use App\Models\Reservation;

class ReservationController extends Controller
{
    public function getReservations() {
        $array = ['error' => '', 'list' => []] ;

        $areas = Area::where('allowed', 1)
                ->get();

               // $areas = tabela com * [piscina, academia, churrasqueira]

        $daysHelper = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

        foreach($areas as $area){
            $dayList = explode(',', $area['days']);
            //$dayList = '0,1,4,5' = [0, 1, 4, 5]


            $dayGroups = [];

            // Adicionando o primeiro dia
            $lastDay = intval(current($dayList));
            // $lastDay = 0


            $dayGroups[] = $daysHelper[$lastDay];
            // $dayGroups = ['Dom'], ==> output dias.da.semana->1o indice


            array_shift($dayList);
            // $dayList = [1,4,5]



            // Adicionando dias relevantes
            foreach ($dayList as $day) {
                if (intval($day) != $lastDay + 1) {
                    // se 1 != 0+1 ==> output false

                    $dayGroups[] = $daysHelper[$lastDay];
                    // $dayGroups = ['Dom', 'Seg'] ==> adiciona o indice 1 do array de dias da semanas
                    $dayGroups[] = $daysHelper[$day];
                    // $dayGroups = ['Dom', 'Seg', 'Qui'] ==> adiciona o indice 3 dia da semana
                }

                $lastDay = intval($day);
                // 1a vez -==> $lastDay = 1
                // 2a vez ==> 4
                // 3a vez ==> 5
            }


            // Adicionando o ultimo dia
            $dayGroups[] = $daysHelper[end($dayList)];
            // $dayGroups = ['Dom', 'Seg', 'Qua', 'Qui'] ==> adiciona o ultimo indice dos arrys

            // Juntando as datas (dia1-dia2)
            $dates = '';
            $close = 0;
            foreach($dayGroups as $group) {
                if ($close === 0) {
                    $dates .= $group;
                } else {
                    $dates .= '-'.$group.',';
                }
                $close = 1 - $close;
            }

            // $dates = 

            $dates = explode(',', $dates);
            array_pop($dates);

            //  Adicionando o TIME
            $start = date('H:i', strtotime($area['start_time']));
            $end = date('H:i', strtotime($area['end_time']));

            foreach($dates as $dKey => $dValue){
                $dates[$dKey] .= ' '. $start .' às ' .$end;

            }
            $array['list'][] = [
                'id' => $area['id'],
                'cover' => asset('storage/'. $area['cover']),
                'title' => $area['title'],
                'dates' => $dates
            ];
           
        }
        

        return $array;
    }

    public function setReservation($id, Request $request){
        $array = ['error' => ''] ;
        
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i:s',
            'property' => 'required'
            
        ]);
        
        if (!$validator->fails()) {
            $date = $request->input('date');
            $time = $request->input('time');
            $property = $request->input('property');

            $unit = Unit::find($property);
            $area = Area::find($id);

            if ($unit && $area) {
                $can = true;

                $weekday = date('w', strtotime($date));
                
                // Verficiar se esta dentro da semana padrao
                $allowedDays = explode(',', $area['days']);

                if (!in_array($weekday, $allowedDays)) {
                    $can = false;
                } else {
                    $start = strtotime($area['start_time']);
                    $end = strtotime('-1 hour',strtotime($area['end_time']));
                    $reservationTime = strtotime($time);
                    
                    if ($reservationTime < $start || $reservationTime > $end) {
                        $can = false;
                    }
                }

                // Verficiar se esta fora dos DisabledDays
                $existingDisabledDay = AreaDisabledDay::where('id_area', $id)
                    ->where('day', $date)
                    ->count();

                    if($existingDisabledDay > 0){
                        $can = false;

                    }

                    
                    // Verificar se não existe reserva no mesmo dia e hora
                $existingReservations = Reservation::where('id_area', $id)
                ->where('reservation_date', $date.' '.$time)
                ->count();
                
                if($existingReservations > 0){
                    $can = false;
                }
                
                
                if ($can){
                    $newReservation = new Reservation();
                    $newReservation->id_unit = $property;
                    $newReservation->id_area = $id;
                    $newReservation->reservation_date = $date.' '. $time;
                    $newReservation->save();
                    
                } else {
                    $array['error'] = "Reserva não permitida nesta data";
                }
                

            } else {
                $array['error'] = "Dados incorretos.";
            }
            

            
        } else {
            $array['error'] = $validator->errors()->first();
            return $array;
        }
        
        
        return $array;
        
        
    }


    public function getDisabeldDates($id){
        $array = ['error' => '', 'list' => []];
        

        $area = Area::find($id);
        if($area) {

            $disabledDays = AreaDisabledDay::where('id_area', $id)->get();
            foreach ($disabledDays as $disabledDay) {
                $array['list'][] = $disabledDay['day']; 
    
            }

            // Dias disabled atraves do allowed
            $allowedDays = explode(',', $area['days']);
            $offDays = [];

            for($q=0;$q<7;$q++){
                if (!in_array($q, $allowedDays)) {
                    $offDays[]=$q;
                }
            }

            // Listar os dias proibidos 3 meses para frente
            $start = time();
            $end = strtotime('+3 months');          

            for($current = $start; $current < $end; $current = strtotime('+1 day', $current)){
                $wd = date('w', $current);
                
                if (in_array($wd, $offDays)) {
                    $array['list'][] = date('Y-m-d', $current);
                }
            }

        } else {
            $array['error'] = "Area inexistente";
            return $array;
            
        }  


        return $array;
    }

    public function getTimes($id, Request $request){
        $array = ['error' => '', 'list' => []];

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d'           
        ]);

        if (!$validator->fails()) {
            $date = $request->input('date');

            $area = Area::find($id);

            if($area){
                $can = true;

                // Varificar se eh dia disabled
                $existingDisabledDay = AreaDisabledDay::where('id_area', $id)
                    ->where('day', $date)
                    ->count();

                if($existingDisabledDay > 0) {
                    $can = false;
                }


                // verifica se eh dia permitido
                $allowedDays = explode(',', $area['days']);
                $weekday = date('w', strtotime($date));
                if(!in_array($weekday, $allowedDays)){
                    $can = false;

                }

                if ($can) {
                    $start = strtotime($area['start_time']);
                    $end = strtotime($area['end_time']);

                    $times = [];

                    for($lastTime = $start; $lastTime < $end; $lastTime = strtotime('+1 hour', $lastTime)) {
                        $times[] = $lastTime;                        
                    }

                    $timeList = [];
                    foreach($times as $time){
                        $timeList[ ]= [ 
                            'id' => date('H:i:s', $time),
                            'title' => date('H:i', $time).' - '.date('H:i', strtotime('+1 hour', $time))
                        ];
                    }

                    // Removendo as reservas
                    $reservations = Reservation::where('id_area', $id)
                    ->whereBetween('reservation_date',[
                        $date.' 00:00:00',
                        $date.' 23:59:59'
                    ])
                    ->get();

                    $toRemove = [];
                    foreach($reservations as $reservation){
                        $time = date('H:i:s', strtotime($reservation['reservation_date']));
                        $toRemove[] = $time;

                    }

                    foreach($timeList as $timeItem) {
                        if(!in_array($timeItem['id'], $toRemove)){
                            $array[ 'list'][] = $timeItem;
                        }

                    }

                }                

            } else {
                $array['error'] = 'Area Inexistente';
                return $array;

            }

        } else {
            $array['error'] = $validator->errors()->first();
            return $array;
        }

        return $array;

    }


    public function getMyReservations(Request $request) {
        $array = ['error'=>'', 'list' => []];

        $property = $request->input('property');
        
        if($property){
            $unit = Unit::find($property);

            if ($unit) {

                $reservations = Reservation::where('id_unit', $property)
                    ->orderBy('reservation_date', 'desc')
                    ->get();

                foreach($reservations as $reservation) {
                    $area = Area::find($reservation['id_area']);

                    $daterev = date('d/m/Y H:i', strtotime($reservation['reservation_date']));

                    $aftertime = date('H:i', strtotime('+1 hour', strtotime($reservation['reservation_date'])));

                    $daterev .= ' à '. $aftertime;

                    $array['list'][]= [
                        'id' => $reservation['id'],
                        'id_area' => $reservation['id_area'],
                        'title' => $reservation['title'],
                        'cover' => asset('storage/'.$area['cover']),
                        'datereserved' => $daterev
                    ];

                }

            } else {
                $array['error'] = 'Propriedade inexistente';
                return $array;
            }

        } else {
            $array['error'] = 'Propriedade necessaria';
            return $array;
        }

        return $array;
    }

    public function delMyReservation($id){
        $array = ['error' => ''];

        $user = auth()->user();
        $reservation = Reservation::find($id);

        if ( $reservation ) {
            $unit = Unit::where('id', $reservation['id_unit'])
                ->where('id_owner', $user['id'])
                ->count();

            if ($unit > 0) {
                Reservation::find($id)->delete();
            } else {
                $array['error'] = "Esta reserva nao é sua";
                return $array;
            }


        }else {
            $array ['error'] = "Reserva inexistente";
        }

        return $array;
        
    }
}

