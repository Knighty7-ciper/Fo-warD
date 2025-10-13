<?php
/**
 * Local Live Class Configuration
 * Simple WebRTC-based video conferencing without Zoom
 */

class LiveClassHandler {
    private $db;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    /**
     * Create a live class session
     */
    public function createLiveClass($course_id, $teacher_id, $title, $start_time, $duration) {
        $room_id = $this->generateRoomId();
        
        $sql = "INSERT INTO live_classes (course_id, teacher_id, title, room_id, start_time, duration, status, created_at) 
                VALUES (:course_id, :teacher_id, :title, :room_id, :start_time, :duration, 'scheduled', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':course_id' => $course_id,
            ':teacher_id' => $teacher_id,
            ':title' => $title,
            ':room_id' => $room_id,
            ':start_time' => $start_time,
            ':duration' => $duration
        ]);
        
        $class_id = $this->db->lastInsertId();
        
        return [
            'success' => true,
            'class_id' => $class_id,
            'room_id' => $room_id,
            'join_url' => $this->getJoinUrl($room_id),
            'start_url' => $this->getStartUrl($room_id, $teacher_id)
        ];
    }
    
    /**
     * Generate unique room ID
     */
    private function generateRoomId() {
        return 'room_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Get join URL for students
     */
    private function getJoinUrl($room_id) {
        return '/frontend/live-class/join.php?room=' . $room_id;
    }
    
    /**
     * Get start URL for teachers
     */
    private function getStartUrl($room_id, $teacher_id) {
        $token = hash('sha256', $room_id . $teacher_id . time());
        return '/frontend/live-class/host.php?room=' . $room_id . '&token=' . $token;
    }
    
    /**
     * Get live class details
     */
    public function getLiveClass($class_id) {
        $sql = "SELECT * FROM live_classes WHERE id = :class_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':class_id' => $class_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Start live class
     */
    public function startLiveClass($class_id, $teacher_id) {
        $sql = "UPDATE live_classes 
                SET status = 'live', started_at = NOW() 
                WHERE id = :class_id AND teacher_id = :teacher_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':class_id' => $class_id,
            ':teacher_id' => $teacher_id
        ]);
    }
    
    /**
     * End live class
     */
    public function endLiveClass($class_id, $teacher_id) {
        $sql = "UPDATE live_classes 
                SET status = 'completed', ended_at = NOW() 
                WHERE id = :class_id AND teacher_id = :teacher_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':class_id' => $class_id,
            ':teacher_id' => $teacher_id
        ]);
    }
    
    /**
     * Record attendance
     */
    public function recordAttendance($class_id, $user_id, $join_time) {
        $sql = "INSERT INTO class_attendance (class_id, user_id, join_time) 
                VALUES (:class_id, :user_id, :join_time)
                ON CONFLICT (class_id, user_id) DO UPDATE SET join_time = :join_time";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':class_id' => $class_id,
            ':user_id' => $user_id,
            ':join_time' => $join_time
        ]);
    }
}
?>
