const StudentAPI = {
    getExams() {
        return ApiClient.get("../php/exam_api.php?action=get_exams");
    },

    getHistory() {
        return ApiClient.get("../php/exam_api.php?action=get_student_history");
    },

    joinExam(code) {
        return ApiClient.post("../php/exam_api.php", {
            action: "join_exam",
            exam_code: code
        });
    }
};